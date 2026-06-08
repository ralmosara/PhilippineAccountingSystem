# Sales Module

Bounded context: **customers, sales invoices, official receipts, POS, EIS submission pipeline.**

This is the highest-stakes BIR-facing module — every issued document carries a sequential number that the BIR audits, and every posted invoice triggers an automatic JV plus an EIS submission row.

---

## Layout

```
app/Modules/Sales/
├── routes.php
├── README.md
├── Domain/
│   ├── ValueObjects/{CustomerId, SalesInvoiceId, OfficialReceiptId}.php
│   ├── Entities/{Customer, SalesInvoice, SalesInvoiceLine, OfficialReceipt}.php
│   ├── Services/VatCalculator.php
│   ├── Events/{InvoiceIssued, InvoiceVoided, OfficialReceiptIssued}.php
│   └── Exceptions/InvoiceAlreadyVoidedException.php
├── Application/
│   ├── Contracts/{Customer, SalesInvoice, OfficialReceipt}RepositoryContract.php
│   ├── Actions/
│   │   ├── CreateCustomer.php
│   │   ├── IssueSalesInvoice.php           ← orchestrates Accounting cross-module
│   │   ├── VoidSalesInvoice.php            ← uses Accounting's ReverseJournalEntry
│   │   └── IssueOfficialReceipt.php
│   └── Exceptions/{CustomerNotFound, InvoiceNotFound}Exception.php
├── Infrastructure/
│   ├── Persistence/
│   │   ├── Eloquent/{Customer, CustomerAddress, SalesInvoice, SalesInvoiceLine, OfficialReceipt}Model.php
│   │   ├── EloquentCustomerRepository.php
│   │   ├── EloquentSalesInvoiceRepository.php
│   │   └── EloquentOfficialReceiptRepository.php
│   ├── Listeners/EnqueueEisSubmission.php  ← reacts to InvoiceIssued, queues EIS
│   ├── Jobs/TransmitInvoiceToEisJob.php    ← Horizon: queue 'eis-priority'
│   └── Providers/SalesServiceProvider.php
└── Presentation/Http/
    ├── Controllers/
    │   ├── CustomerController.php                 ← 7 RESTful methods
    │   ├── SalesInvoiceController.php             ← 7 RESTful methods (read-only ops)
    │   ├── IssueSalesInvoiceController.php        ← __invoke
    │   ├── VoidSalesInvoiceController.php         ← __invoke (MFA required)
    │   ├── IssueOfficialReceiptController.php     ← __invoke
    │   └── TransmitInvoiceToEisController.php     ← __invoke (manual retransmit)
    ├── Requests/{StoreCustomer, IssueSalesInvoice, VoidSalesInvoice, IssueOfficialReceipt}Request.php
    └── Resources/{Customer, SalesInvoice, OfficialReceipt}Resource.php
```

---

## End-to-end issuance flow (the canonical cross-module example)

```
POST /api/v1/sales-invoices/issue
  → IssueSalesInvoiceController::__invoke         ← single-action
  → IssueSalesInvoice::execute (Sales\Application)
    │
    ├─ 1. CustomerRepositoryContract::findById(...)              [Sales]
    │
    ├─ 2. SalesInvoiceRepositoryContract::allocateDocNo(...)     [Sales]
    │     → SELECT accounting.allocate_doc_no(?::uuid)           [Postgres function, row-locked]
    │     → 'SI-2026-000001'
    │
    ├─ 3. VatCalculator::compute(lines, customer)                [Sales\Domain]
    │     → handles standard 12% VAT, zero-rated, exempt
    │     → senior/PWD: 20% discount + VAT exempt
    │     → government: 5% withheld VAT
    │
    ├─ 4. SalesInvoiceRepositoryContract::save($invoice)         [Sales]
    │     → INSERT sales.sales_invoices (status: draft)
    │
    ├─ 5. CreateJournalEntry::execute(...)                       [Accounting\Application]
    │     → DR Accounts Receivable (total)
    │     → CR Sales — Vatable / Zero / Exempt (per line)
    │     → CR Output VAT (vat_amount)
    │     → INSERT accounting.journal_entries + lines (draft)
    │     → audit.write_event('journalentry.drafted')
    │
    ├─ 6. PostJournalEntry::execute(...)                         [Accounting\Application]
    │     → DoubleEntryValidator: debits = credits ✓
    │     → posted_at set
    │     → accounting.journal_must_balance_at_commit fires at COMMIT ✓
    │     → audit.write_event('journalentry.posted')
    │     → dispatch JournalPosted event
    │
    ├─ 7. SalesInvoice::post() + repo.save()
    │     → posted_at set; journal_entry_id linked
    │
    ├─ 8. audit.write_event('salesinvoice.issued')               [hash-chained]
    │
    └─ 9. dispatch InvoiceIssued event
        │
        └→ EnqueueEisSubmission listener (Sales\Infrastructure)
           ├─ INSERT tax.eis_submissions (status: 'pending')
           └─ if BIR_EIS_ENABLED:
                TransmitInvoiceToEisJob::dispatch()->onQueue('eis-priority')
                ├─ build BIR-mandated JSON payload
                ├─ sign with X.509 cert (BIR_EIS_CERT_PATH)
                ├─ POST to BIR EIS API
                ├─ on 2xx: status='acknowledged', store ack_no, QR url
                └─ on failure: log eis_retries; exponential backoff up to 24h
```

---

## BIR rules implemented

| Rule | Where |
|---|---|
| Sequential numbering, no gaps | `accounting.allocate_doc_no()` row-lock allocator |
| Voids preserve numbers | `voided_at` timestamp; sequence_no never recycled |
| No DELETE on issued invoices | `REVOKE DELETE ON sales.sales_invoices FROM PUBLIC` |
| Senior / PWD: 20% discount + VAT exempt | `VatCalculator` (RA 9994 / RA 10754) |
| Government: 5% withheld VAT | `VatCalculator` (NIRC Sec. 114(C)) |
| EIS transmission within 3 days | `TransmitInvoiceToEisJob` retry policy (RR 8-2022) |
| Audit trail of every issuance + void | `AuditWriterContract` calls in every Action |

---

## Cross-module pattern

Sales depends on:
- `App\Modules\Accounting\Application\Actions\CreateJournalEntry`
- `App\Modules\Accounting\Application\Actions\PostJournalEntry`
- `App\Modules\Accounting\Application\Actions\ReverseJournalEntry`
- `App\Modules\Audit\Application\Contracts\AuditWriterContract`

Sales does NOT touch:
- Accounting's Eloquent models, repositories, or domain entities
- Audit's Postgres writer directly

This keeps the seams clean for eventual extraction — `IssueSalesInvoice` could be moved to a separate microservice and just need new HTTP-based adapters for the Accounting and Audit contracts.
