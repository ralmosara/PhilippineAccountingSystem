# Procurement Module

Bounded context: **vendors, purchase orders, GRNs, vendor bills with 3-way match, withholding tax, BIR Form 2307 issuance.**

This is the AP counterpart to the Sales module. The headline feature is **automatic 2307 issuance**: when a vendor bill posts and the vendor has a default ATC code, a `tax.form_2307` row is created in the same transaction by an event listener — no extra step required.

---

## Layout

```
app/Modules/Procurement/
├── routes.php
├── README.md
├── Domain/
│   ├── ValueObjects/{VendorId, PurchaseOrderId, VendorBillId}.php
│   ├── Entities/{Vendor, VendorBill, VendorBillLine}.php
│   ├── Services/
│   │   ├── WithholdingTaxCalculator.php       ← BIR ATC-driven WT computation
│   │   └── ThreeWayMatcher.php                ← PO ↔ GRN ↔ Bill match with tolerance
│   ├── Events/{VendorBillPosted, Form2307Issued}.php
│   └── Exceptions/{VendorBillAlreadyPosted, ThreeWayMatchVariance}Exception.php
├── Application/
│   ├── Contracts/
│   │   ├── VendorRepositoryContract.php
│   │   ├── VendorBillRepositoryContract.php
│   │   └── AtcCodeProviderContract.php        ← consumed from Tax module
│   ├── Actions/
│   │   ├── CreateVendor.php
│   │   ├── PostVendorBill.php                 ← orchestrates Accounting + Audit
│   │   └── IssueForm2307.php                  ← idempotent
│   └── Exceptions/{VendorNotFound, VendorBillNotFound}Exception.php
├── Infrastructure/
│   ├── Persistence/
│   │   ├── Eloquent/{Vendor, PurchaseOrder, PurchaseOrderLine, VendorBill, VendorBillLine}Model.php
│   │   ├── EloquentVendorRepository.php
│   │   └── EloquentVendorBillRepository.php
│   ├── Listeners/AutoIssueForm2307.php        ← reacts to VendorBillPosted
│   └── Providers/ProcurementServiceProvider.php
└── Presentation/Http/
    ├── Controllers/
    │   ├── VendorController.php                ← 7 RESTful methods
    │   ├── PurchaseOrderController.php         ← 7 RESTful methods
    │   ├── VendorBillController.php            ← 7 RESTful methods (read-only ops)
    │   ├── PostVendorBillController.php        ← __invoke (MFA required)
    │   └── IssueForm2307Controller.php         ← __invoke (manual / re-issue)
    ├── Requests/{StoreVendor, PostVendorBill}Request.php
    └── Resources/{Vendor, VendorBill, PurchaseOrder}Resource.php
```

---

## End-to-end posting flow with automatic 2307 issuance

```
POST /api/v1/vendor-bills/post
  body: {
    vendor_id, vendor_invoice_no, vendor_invoice_date, bill_date,
    ap_account_id, vat_input_account_id, withholding_payable_account_id,
    jv_document_series_id, lines: [...],
    atc_code_override?      ← optional: override vendor.default_atc_code
  }
  → PostVendorBillController::__invoke
  → PostVendorBill::execute
    │
    ├─ 1. VendorRepositoryContract::findById                    [Procurement]
    ├─ 2. AtcCodeProviderContract::find(vendor.default_atc_code) [Tax — via contract]
    ├─ 3. WithholdingTaxCalculator::compute(subtotal, atc, rate)
    │     → tax_withheld = subtotal × rate (e.g. ₱10,000 × 5% = ₱500)
    ├─ 4. VendorBillRepository::save (status: draft)
    │
    ├─ 5. CreateJournalEntry::execute(...)                       [Accounting]
    │     → DR Expense Accounts            (subtotal)
    │     → DR Input VAT                   (vat_input)
    │     → CR AP — Vendor                 (net payable)
    │     → CR Withholding Tax Payable     (tax_withheld)
    │
    ├─ 6. PostJournalEntry::execute(...)                         [Accounting]
    │     → balance constraint passes at COMMIT ✓
    │
    ├─ 7. VendorBill::post() + repo.save()
    │     → posted_at set, journal_entry_id linked
    │
    ├─ 8. audit.write_event('vendorbill.posted')                 [hash-chained]
    │
    └─ 9. dispatch VendorBillPosted event
        │
        └→ AutoIssueForm2307 listener (synchronous, same tx)
           ├─ if hasWithholding(): IssueForm2307::execute
           │  ├─ INSERT tax.form_2307 (idempotent — returns existing if any)
           │  ├─ audit.write_event('form2307.issued')
           │  └─ dispatch Form2307Issued event
           └─ else: no-op
```

**Why synchronous (not queued):** the 2307 record is BIR-mandatory evidence of the withholding. If we can't write it, the bill posting itself should roll back — keeping the cert and the WT entry inseparable. The PDF is rendered lazily on download (out-of-tx); the row is in-tx.

---

## Withholding Tax — defaults & overrides

Resolution order:
1. `atc_code_override` from the request (header-level)
2. `vendor.default_atc_code`
3. None → no withholding

The rate comes from `tax.atc_codes` via the `AtcCodeProviderContract` — Procurement's interface, Tax's implementation. Cross-module data flow without coupling.

The base for WT is **net of VAT** by default (RR 11-2018), with `WithholdingTaxCalculator::baseFor()` available for the rare cases requiring gross-of-VAT (rentals).

---

## Cross-module pattern (Procurement is the consumer)

```
Procurement\Application\Actions\PostVendorBill
  ├─ uses Procurement\Application\Contracts\*                         (own surface)
  ├─ uses Procurement\Domain\Services\WithholdingTaxCalculator        (own domain)
  ├─ uses Accounting\Application\Actions\{CreateJournalEntry, PostJournalEntry}   ← cross-module
  ├─ uses Audit\Application\Contracts\AuditWriterContract             ← cross-module
  └─ uses Procurement\Application\Contracts\AtcCodeProviderContract   ← consumer-driven contract,
                                                                          implemented by Tax module
```

The `AtcCodeProviderContract` lives in Procurement's `Application/Contracts/` because Procurement is the consumer that defines what shape it needs. The Tax module provides `EloquentAtcCodeProvider` as the implementation. This is the **consumer-driven contracts** pattern — Procurement isn't dependent on Tax, Tax fulfills Procurement's contract.

---

## BIR rules implemented

| Rule | Where |
|---|---|
| EWT computation per ATC code | `WithholdingTaxCalculator` × `tax.atc_codes` rates |
| Form 2307 issuance per WT bill | `AutoIssueForm2307` listener (synchronous, same tx) |
| 2307 idempotency on retries | `IssueForm2307::execute` checks for existing row |
| Quarterly aggregation for SAWT | `tax.form_2307` periods feed SAWT DAT exporter (Tax module — coming) |
| 3-way match with tolerance | `ThreeWayMatcher` (5% price tolerance default) |
| No DELETE on posted bills | `REVOKE DELETE ON procurement.vendor_bills FROM PUBLIC` |

---

## Routes

```
GET    /api/v1/vendors                   index
POST   /api/v1/vendors                   store           (CreateVendor)
GET    /api/v1/vendors/{vendor}          show
PATCH  /api/v1/vendors/{vendor}          update
DELETE /api/v1/vendors/{vendor}          destroy         (soft-disable)

GET    /api/v1/purchase-orders           index
GET    /api/v1/purchase-orders/{po}      show
…etc

GET    /api/v1/vendor-bills              index
GET    /api/v1/vendor-bills/{bill}       show
POST   /api/v1/vendor-bills/post                          single-action: post + auto-2307  (MFA)
POST   /api/v1/vendor-bills/{bill}/issue-2307             single-action: manual/re-issue   (MFA)
```
