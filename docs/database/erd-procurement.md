# Procurement Schema — `procurement.*`

**Purpose:** Vendors, RFQs, purchase orders, goods receipt notes (GRN), vendor bills, 3-way match.
**BIR alignment:** Form 2307 issuance to suppliers (withholding certificate), Purchases Book per CAS.

---

## ERD

```mermaid
erDiagram
    VENDORS ||--o{ VENDOR_ADDRESSES : has
    VENDORS ||--o{ VENDOR_BANK_ACCOUNTS : "for payment"
    VENDORS ||--o{ RFQS : "quoted from"
    VENDORS ||--o{ PURCHASE_ORDERS : "ordered from"
    VENDORS ||--o{ VENDOR_BILLS : "billed by"
    VENDORS ||--o{ FORM_2307 : "received WT certificate"

    RFQS ||--o{ RFQ_LINES : has
    RFQS ||--o| PURCHASE_ORDERS : converts_to
    PURCHASE_ORDERS ||--|{ PURCHASE_ORDER_LINES : has
    PURCHASE_ORDERS ||--o{ GOODS_RECEIPT_NOTES : received_via
    GOODS_RECEIPT_NOTES ||--|{ GRN_LINES : has
    PURCHASE_ORDERS ||--o{ VENDOR_BILLS : invoiced_via
    VENDOR_BILLS ||--|{ VENDOR_BILL_LINES : has
    VENDOR_BILLS ||--o| FORM_2307 : "WT cert issued (cross-schema)"
    VENDOR_BILLS ||--o{ PAYMENT_VOUCHERS : paid_via
    PAYMENT_VOUCHERS ||--|{ PAYMENT_VOUCHER_LINES : applies_to_bills

    VENDORS {
        uuid id PK
        uuid company_id "cross-schema ref"
        string vendor_no UK "VEN-000001"
        string registered_name "BIR-registered"
        string trade_name
        string tin "encrypted"
        bool is_vat_registered
        bool is_government_supplier
        bool is_top_withholding_agent "TWA designation by BIR"
        string default_atc_code "WC010|WC020|WI010..."
        decimal default_withholding_rate
        smallint payment_terms_days
        string default_currency
        string contact_person
        string email
        string phone
        bool is_active
        timestamp created_at
    }
    VENDOR_ADDRESSES {
        uuid id PK
        uuid vendor_id FK
        enum address_type "main|billing|shipping"
        string line1
        string barangay
        string city
        string province
        string postal_code
    }
    VENDOR_BANK_ACCOUNTS {
        uuid id PK
        uuid vendor_id FK
        string bank_name
        string account_no "encrypted"
        string account_holder
        bool is_default
    }
    RFQS {
        uuid id PK
        uuid company_id "cross-schema ref"
        string rfq_no UK "RFQ-2026-000001"
        date rfq_date
        date deadline
        enum status "draft|sent|responses_received|awarded|cancelled"
    }
    RFQ_LINES {
        uuid id PK
        uuid rfq_id FK
        uuid vendor_id FK "nullable; one row per vendor offer"
        string description
        decimal quantity
        decimal quoted_price
        date quoted_lead_time_days
        bool is_winning_bid
    }
    PURCHASE_ORDERS {
        uuid id PK
        uuid vendor_id FK
        uuid document_series_id "cross-schema ref"
        bigint sequence_no UK
        string po_no UK "PO-2026-000001"
        date order_date
        date expected_delivery
        char currency
        decimal fx_rate
        decimal subtotal
        decimal vat_amount
        decimal total
        enum status "draft|approved|sent|partial|fulfilled|cancelled"
        timestamp approved_at
        uuid approved_by
        text remarks
    }
    PURCHASE_ORDER_LINES {
        uuid id PK
        uuid purchase_order_id FK
        smallint line_no
        uuid item_id "cross-schema ref"
        string description
        decimal quantity
        decimal received_quantity "running total from GRN"
        decimal billed_quantity "running total from vendor bills"
        decimal unit_price
        decimal line_total
        uuid expense_account_id "cross-schema ref"
        uuid project_id "cross-schema ref"
    }
    GOODS_RECEIPT_NOTES {
        uuid id PK
        uuid purchase_order_id FK
        string grn_no UK
        date received_date
        uuid warehouse_id "cross-schema ref"
        uuid received_by
        text remarks
    }
    GRN_LINES {
        uuid id PK
        uuid grn_id FK
        uuid po_line_id FK
        decimal received_quantity
        decimal accepted_quantity
        decimal rejected_quantity
        text rejection_reason
    }
    VENDOR_BILLS {
        uuid id PK
        uuid vendor_id FK
        uuid purchase_order_id FK "nullable for non-PO bills"
        string vendor_invoice_no "supplier's SI/OR number"
        date vendor_invoice_date
        date bill_date "received date by accounting"
        date due_date
        char currency
        decimal fx_rate
        decimal subtotal
        decimal vat_input "12% input VAT (claimable)"
        decimal vat_input_deferred "capital goods if >= 1M, amortized 60 mo"
        decimal withholding_amount
        string withholding_atc_code
        decimal withholding_rate
        decimal total
        decimal php_total
        timestamp posted_at
        uuid journal_entry_id "cross-schema ref"
        timestamp three_way_matched_at "PO + GRN + Bill match"
        bool match_status "matched|variance|unmatched"
        timestamp voided_at
        text void_reason
        timestamp created_at
    }
    VENDOR_BILL_LINES {
        uuid id PK
        uuid vendor_bill_id FK
        smallint line_no
        uuid po_line_id FK "nullable"
        uuid item_id "cross-schema ref"
        string description
        decimal quantity
        decimal unit_price
        decimal vat_amount
        decimal line_total
        uuid expense_account_id "cross-schema ref"
        uuid tax_code_id "cross-schema ref"
        uuid project_id "cross-schema ref"
    }
    PAYMENT_VOUCHERS {
        uuid id PK
        uuid document_series_id "cross-schema ref"
        bigint sequence_no UK
        string cv_no UK "CV-2026-000001"
        date payment_date
        enum payment_method "cash|check|bank_transfer|wire"
        string reference_no "check no, txn id"
        char currency
        decimal fx_rate
        decimal total_amount
        timestamp posted_at
        uuid journal_entry_id "cross-schema ref"
        text remarks
    }
    PAYMENT_VOUCHER_LINES {
        uuid id PK
        uuid payment_voucher_id FK
        uuid vendor_bill_id FK
        decimal applied_amount
    }
```

---

## Tables (summary)

| Table | Rows (est.) | Critical indexes | Notes |
|---|---|---|---|
| `vendors` | ~500 | PK, UK(vendor_no), UK(tin) | TIN encrypted; `is_top_withholding_agent` from BIR list |
| `vendor_addresses` | ~1k | PK, idx(vendor_id) | |
| `vendor_bank_accounts` | ~600 | PK, idx(vendor_id) | Account # encrypted |
| `rfqs` | ~500/year | PK, UK(rfq_no) | |
| `rfq_lines` | ~3k/year | PK, idx(rfq_id, vendor_id) | |
| `purchase_orders` | ~5k/year | PK, UK(po_no), idx(vendor_id, order_date), idx(status) | |
| `purchase_order_lines` | ~20k/year | PK, idx(purchase_order_id, line_no) | `received_quantity` and `billed_quantity` updated by triggers |
| `goods_receipt_notes` | ~5k/year | PK, UK(grn_no), idx(purchase_order_id) | Triggers stock_movement insert |
| `grn_lines` | ~20k/year | PK, idx(grn_id, po_line_id) | |
| `vendor_bills` | ~10k/year | PK, UK(vendor_id, vendor_invoice_no), idx(vendor_id, bill_date), idx(posted_at), idx(match_status) | 3-way match status flagged |
| `vendor_bill_lines` | ~30k/year | PK, idx(vendor_bill_id) | |
| `payment_vouchers` | ~5k/year | PK, UK(cv_no), idx(payment_date) | |
| `payment_voucher_lines` | ~10k/year | PK, idx(vendor_bill_id) | |

---

## 3-Way Match Logic

A bill is `matched` when **all** are true (per line):
```
po.received_quantity ≥ bill.quantity   (cannot bill more than received)
po.unit_price        = bill.unit_price (or within tolerance %)
bill.quantity        = grn.accepted_quantity (referenced GRN)
```

Variance flags `match_status = 'variance'` and routes to Approver for override (logged in audit).
Bills with `purchase_order_id IS NULL` (direct bills, e.g. utilities) skip matching but require explicit approval.

---

## Withholding Tax Engine

On `vendor_bills.posted_at`:
1. Look up `vendor.default_atc_code` (or override at line level)
2. Look up rate from `tax.atc_codes`
3. Compute `withholding_amount = subtotal × rate` (excluding VAT)
4. Generate `tax.form_2307` row with:
   - `vendor_bill_id`, `vendor_id`, `atc_code`, `income_payment`, `tax_withheld`, `period_from/to`
5. PDF generated lazily on demand or in batch quarterly
6. Vendor receives 2307 PDF via email or download

---

## Triggers

### 1. PO line received quantity rollup
```sql
CREATE TRIGGER grn_line_po_rollup
    AFTER INSERT OR UPDATE OR DELETE ON procurement.grn_lines
    FOR EACH ROW EXECUTE FUNCTION procurement.recalc_po_received_qty();
```

### 2. Audit emission on bill posting
```sql
CREATE TRIGGER vendor_bills_audit
    AFTER INSERT OR UPDATE OF posted_at, voided_at ON procurement.vendor_bills
    FOR EACH ROW EXECUTE FUNCTION audit.write_event('VendorBill');
```

### 3. Stock movement on GRN
GRN insert triggers `inventory.stock_movements` insert with `movement_type='receipt'`.

---

## Cross-Schema References

**Outbound:**
- `vendor_bill_lines.item_id` → `inventory.items.id`
- `vendor_bill_lines.expense_account_id` → `accounting.accounts.id`
- `vendor_bill_lines.tax_code_id` → `accounting.tax_codes.id`
- `vendor_bills.journal_entry_id` → `accounting.journal_entries.id`
- `purchase_orders.document_series_id` → `accounting.document_series.id`

**Inbound:**
- `tax.form_2307.vendor_bill_id` ← issued certificate
- `inventory.stock_movements.source_doc_id` ← when GRN deducts stock
