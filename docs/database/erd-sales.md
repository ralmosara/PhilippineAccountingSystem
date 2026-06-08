# Sales Schema — `sales.*`

**Purpose:** Customers, sales invoices (SI), official receipts (OR), POS terminals, document series for BIR sequential numbering, sales returns.
**Owned by role:** `pha_app`
**BIR alignment:** RR 18-2012 (OR/SI), RR 8-2022 (EIS), RR 9-2009 (CAS sequential numbering)

---

## ERD

```mermaid
erDiagram
    CUSTOMERS ||--o{ CUSTOMER_ADDRESSES : has
    CUSTOMERS ||--o{ SALES_INVOICES : billed
    CUSTOMERS ||--o{ QUOTATIONS : "quoted to"
    CUSTOMERS ||--o{ SALES_ORDERS : "ordered by"
    QUOTATIONS ||--o| SALES_ORDERS : converts_to
    SALES_ORDERS ||--o{ SALES_INVOICES : invoices

    DOCUMENT_SERIES ||--o{ SALES_INVOICES : numbers
    DOCUMENT_SERIES ||--o{ OFFICIAL_RECEIPTS : numbers
    SALES_INVOICES ||--|{ SALES_INVOICE_LINES : has
    SALES_INVOICES ||--o{ OFFICIAL_RECEIPTS : "may collect"
    SALES_INVOICES ||--o{ SALES_RETURNS : "returned via"
    SALES_INVOICES ||--o{ EIS_SUBMISSIONS : "transmitted (cross-schema)"

    POS_TERMINALS ||--o{ POS_SHIFTS : runs
    POS_SHIFTS ||--o{ SALES_INVOICES : "issued during"
    POS_TERMINALS ||--o{ POS_OFFLINE_QUEUE : "buffers"

    CUSTOMERS {
        uuid id PK
        uuid company_id "cross-schema ref"
        string customer_no UK "auto: CUST-000001"
        string registered_name "BIR-registered name"
        string trade_name
        string tin "format: 000-000-000-000 or 000-000-000-000-000 with branch"
        bool is_vat_registered
        bool is_government "5% withheld VAT applies"
        bool is_senior_citizen "20% discount + VAT exempt"
        bool is_pwd "20% discount + VAT exempt"
        string id_type "for senior/PWD"
        string id_number
        string email
        string phone
        decimal credit_limit
        smallint payment_terms_days "0=COD, 30=Net30..."
        string default_currency
        bool is_active
        timestamp created_at
    }
    CUSTOMER_ADDRESSES {
        uuid id PK
        uuid customer_id FK
        enum address_type "billing|shipping"
        string line1
        string line2
        string barangay
        string city
        string province
        string region
        string postal_code
        bool is_default
    }
    QUOTATIONS {
        uuid id PK
        uuid customer_id FK
        string quote_no UK "QT-2026-000001"
        date quote_date
        date valid_until
        decimal subtotal
        decimal vat_amount
        decimal discount_amount
        decimal total
        enum status "draft|sent|accepted|rejected|expired"
    }
    SALES_ORDERS {
        uuid id PK
        uuid customer_id FK
        uuid quotation_id FK
        string so_no UK "SO-2026-000001"
        date order_date
        date required_date
        decimal total
        enum status "draft|confirmed|partial|fulfilled|cancelled"
    }
    DOCUMENT_SERIES {
        uuid id PK
        uuid company_id "cross-schema ref"
        uuid branch_id "cross-schema ref"
        enum document_type "OR|SI|CASH_INVOICE|CHARGE_INVOICE|CREDIT_MEMO|DEBIT_MEMO"
        string prefix "e.g. OR-2026-"
        bigint next_sequence "row-locked allocator"
        bigint series_start "BIR-registered range start"
        bigint series_end "BIR-registered range end"
        string bir_atp_no "Authority to Print number / ASTRA reference"
        date atp_date
        timestamp activated_at
        timestamp exhausted_at
        bool is_active
    }
    SALES_INVOICES {
        uuid id PK
        uuid customer_id FK
        uuid sales_order_id FK
        uuid document_series_id FK
        bigint sequence_no UK "no gaps; voided invoices keep number"
        string doc_no UK "SI-2026-000001"
        enum doc_kind "cash|charge"
        date invoice_date
        date due_date
        char currency
        decimal fx_rate
        decimal subtotal
        decimal vat_exempt_sales
        decimal vat_zero_rated_sales
        decimal vatable_sales
        decimal vat_amount "12% of vatable_sales"
        decimal discount_amount
        decimal senior_pwd_discount "20% if applicable"
        decimal withheld_vat "5% for government customers"
        decimal total
        decimal php_total "settled at posting"
        timestamp posted_at "links to journal entry"
        uuid journal_entry_id "cross-schema ref"
        timestamp voided_at "soft-void; sequence preserved"
        text void_reason
        uuid voided_by
        uuid posted_by
        timestamp created_at
        timestamp updated_at
    }
    SALES_INVOICE_LINES {
        uuid id PK
        uuid sales_invoice_id FK
        smallint line_no
        uuid item_id "cross-schema ref to inventory.items (nullable for service)"
        string description
        decimal quantity
        decimal unit_price
        decimal discount_pct
        decimal discount_amount
        uuid tax_code_id "cross-schema ref to accounting.tax_codes"
        decimal vat_amount
        decimal line_total
        uuid revenue_account_id "cross-schema ref to accounting.accounts"
        uuid project_id "cross-schema ref"
    }
    OFFICIAL_RECEIPTS {
        uuid id PK
        uuid sales_invoice_id FK "nullable for advance/general receipts"
        uuid customer_id FK
        uuid document_series_id FK
        bigint sequence_no UK
        string doc_no UK "OR-2026-000001"
        date received_date
        decimal amount
        char currency
        decimal fx_rate
        decimal php_amount
        enum payment_method "cash|check|bank_transfer|credit_card|gcash|maya"
        string reference_no "check no, transaction id"
        text remarks
        timestamp voided_at
        text void_reason
        uuid issued_by
        timestamp created_at
    }
    SALES_RETURNS {
        uuid id PK
        uuid sales_invoice_id FK
        string return_no UK "CM-2026-000001 (Credit Memo)"
        date return_date
        decimal amount
        text reason
        timestamp posted_at
    }
    POS_TERMINALS {
        uuid id PK
        uuid branch_id "cross-schema ref"
        string terminal_code UK "TERM-001"
        string device_id "browser fingerprint or hardware id"
        bool is_active
        timestamp last_seen_at
    }
    POS_SHIFTS {
        uuid id PK
        uuid pos_terminal_id FK
        uuid cashier_user_id "cross-schema ref to identity.users"
        timestamp opened_at
        decimal opening_cash
        timestamp closed_at
        decimal closing_cash
        decimal expected_cash
        decimal cash_variance
        text notes
    }
    POS_OFFLINE_QUEUE {
        uuid id PK
        uuid pos_terminal_id FK
        jsonb payload "queued sale awaiting OR sequence allocation"
        timestamp queued_at
        timestamp synced_at
        uuid resulting_invoice_id
    }
```

---

## Tables (summary)

| Table | Rows (est.) | Critical indexes | Notes |
|---|---|---|---|
| `customers` | 100–10k | PK, UK(customer_no), UK(tin) where not null, idx(company_id, is_active) | TIN encrypted via pgcrypto |
| `customer_addresses` | ~2 per customer | PK, idx(customer_id) | Region/province use PSGC codes |
| `quotations` | ~5k/year | PK, UK(quote_no) | |
| `sales_orders` | ~5k/year | PK, UK(so_no), idx(customer_id, order_date) | |
| `document_series` | ~20 (per branch × type) | PK, UK(company_id, branch_id, document_type, prefix) | **BIR-critical** — see invariants |
| `sales_invoices` | ~50k/year | PK, UK(doc_no), UK(document_series_id, sequence_no), idx(customer_id, invoice_date), idx(posted_at) | Partition by RANGE(invoice_date) yearly when > 500k |
| `sales_invoice_lines` | ~150k/year | PK, idx(sales_invoice_id, line_no), idx(item_id) | |
| `official_receipts` | ~50k/year | PK, UK(doc_no), UK(document_series_id, sequence_no) | |
| `sales_returns` | ~500/year | PK, UK(return_no) | Credit memo |
| `pos_terminals` | ~20 | PK, UK(terminal_code) | |
| `pos_shifts` | ~6k/year (terminals × days) | PK, idx(pos_terminal_id, opened_at) | Cash variance reporting |
| `pos_offline_queue` | transient | PK, idx(pos_terminal_id, queued_at) where synced_at is null | Cleared on sync |

---

## Triggers (BIR-critical)

### 1. Sequential numbering allocator
```sql
CREATE OR REPLACE FUNCTION sales.allocate_doc_no(_series_id uuid)
    RETURNS bigint
    LANGUAGE plpgsql AS $$
DECLARE _seq bigint;
BEGIN
    -- Row-lock the series; SERIALIZABLE isolation in caller transaction
    SELECT next_sequence INTO _seq
      FROM sales.document_series
     WHERE id = _series_id
       FOR UPDATE;

    UPDATE sales.document_series
       SET next_sequence = next_sequence + 1,
           exhausted_at = CASE WHEN next_sequence + 1 > series_end THEN now() ELSE exhausted_at END
     WHERE id = _series_id;

    IF _seq > (SELECT series_end FROM sales.document_series WHERE id = _series_id) THEN
        RAISE EXCEPTION 'Document series exhausted; new BIR ATP required';
    END IF;

    RETURN _seq;
END $$;
```

### 2. Void preserves sequence (no DELETE)
```sql
REVOKE DELETE ON sales.sales_invoices, sales.official_receipts FROM pha_app;
-- Voiding sets voided_at; sequence_no remains in place. BIR auditor sees
-- the void reason and the gap-free sequence.
```

### 3. EIS transmission trigger (async)
```sql
CREATE TRIGGER sales_invoice_eis_dispatch
    AFTER UPDATE OF posted_at ON sales.sales_invoices
    FOR EACH ROW
    WHEN (NEW.posted_at IS NOT NULL AND OLD.posted_at IS NULL)
    EXECUTE FUNCTION tax.enqueue_eis_submission();
```

(The function inserts a `tax.eis_submissions` row in `pending` status; a Horizon job picks it up.)

---

## Senior Citizen / PWD Discount Logic

Per RA 9994 (Senior) and RA 10754 (PWD): 20% discount on selected goods/services, **plus** VAT exemption on the discounted amount.

Application in `sales_invoices`:
```
gross           = qty × unit_price                        (per line)
sc_pwd_discount = gross × 0.20    (if customer.is_senior_citizen OR is_pwd)
net_of_discount = gross - sc_pwd_discount
vat_amount      = 0                (VAT exempt for senior/PWD)
total           = net_of_discount
```

For mixed customers (some lines eligible, some not), discount is line-level not invoice-level.

---

## Cross-Schema References

**Outbound:**
- `sales_invoices.journal_entry_id` → `accounting.journal_entries.id`
- `sales_invoice_lines.item_id` → `inventory.items.id`
- `sales_invoice_lines.tax_code_id` → `accounting.tax_codes.id`
- `sales_invoice_lines.revenue_account_id` → `accounting.accounts.id`
- `sales_invoice_lines.project_id` → `projects.projects.id`

**Inbound:**
- `tax.eis_submissions.sales_invoice_id` ← (consumed by EIS gateway)
- `inventory.stock_movements.source_doc_id` ← when sale deducts stock
- `accounting.journal_entries.source_doc_id` ← when invoice posts
