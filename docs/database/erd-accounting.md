# Accounting Schema — `accounting.*`

**Purpose:** Chart of Accounts, journal entries, fiscal years/periods, FX rates, cost centers. The core of the BIR-compliant general ledger.
**Owned by role:** `pha_app`
**BIR alignment:** RR 9-2009 / RR 11-2025 (CAS), PFRS for SMEs / Full PFRS

---

## ERD

```mermaid
erDiagram
    FISCAL_YEARS ||--o{ FISCAL_PERIODS : has
    FISCAL_PERIODS ||--o{ JOURNAL_ENTRIES : scoped_to
    ACCOUNTS ||--o{ ACCOUNTS : "self (parent_id, ltree path)"
    ACCOUNTS ||--o{ JOURNAL_LINES : posted_to
    JOURNAL_ENTRIES ||--|{ JOURNAL_LINES : "1..N debit/credit"
    DOCUMENT_SERIES ||--o{ JOURNAL_ENTRIES : numbers
    TAX_CODES ||--o{ JOURNAL_LINES : tagged_with
    FX_RATES ||--o{ JOURNAL_LINES : converted_via
    COST_CENTERS ||--o{ JOURNAL_LINES : allocated_to
    JOURNAL_ENTRIES ||--o| JOURNAL_ENTRIES : "reversed_by (self FK)"
    RECURRING_TEMPLATES ||--o{ JOURNAL_ENTRIES : generated_from

    FISCAL_YEARS {
        uuid id PK
        uuid company_id "cross-schema ref"
        smallint year_number "e.g. 2026"
        date starts_on
        date ends_on
        timestamp closed_at "BIR-required year-end close"
        uuid closed_by
    }
    FISCAL_PERIODS {
        uuid id PK
        uuid fiscal_year_id FK
        smallint period_number "1..12"
        string label "Jan 2026"
        date starts_on
        date ends_on
        timestamp locked_at "trigger blocks writes when set"
        uuid locked_by
        text lock_reason
    }
    ACCOUNTS {
        uuid id PK
        uuid company_id "cross-schema ref"
        string code UK "PFRS-style: 1100-01-001"
        string name
        enum type "asset|liability|equity|revenue|expense|contra_asset|contra_liability|contra_equity"
        enum normal_balance "debit|credit"
        uuid parent_id FK "self-FK; null = root"
        ltree path "GiST-indexed for hierarchy queries"
        bool is_postable "false for header/group accounts"
        bool is_active
        string description
        string pfrs_classification "current_asset|noncurrent_asset|operating_revenue|..."
        timestamp created_at
        timestamp updated_at
    }
    JOURNAL_ENTRIES {
        uuid id PK
        uuid company_id "cross-schema ref"
        uuid fiscal_period_id FK
        uuid document_series_id FK "BIR sequential numbering"
        bigint sequence_no UK "no gaps within series"
        string doc_no UK "JV-2026-000001"
        date entry_date
        text memo
        enum source "manual|sales|purchase|payroll|cash_receipt|cash_disbursement|recurring|reversal|year_end"
        uuid source_doc_id "polymorphic ref to originating doc"
        string source_doc_type
        timestamp posted_at "null = draft; non-null = posted (immutable)"
        uuid posted_by
        uuid reversed_by FK "links to reversal entry"
        uuid created_by
        uuid updated_by
        timestamp created_at
        timestamp updated_at
    }
    JOURNAL_LINES {
        uuid id PK
        uuid journal_entry_id FK
        smallint line_no
        uuid account_id FK
        char currency "ISO 4217 e.g. PHP"
        decimal debit "numeric(18,4) — at most one of debit/credit non-zero"
        decimal credit "numeric(18,4)"
        decimal fx_rate "numeric(18,8); 1.0 if PHP"
        decimal php_amount "numeric(18,2) settled at posting"
        uuid tax_code_id FK
        uuid project_id "cross-schema ref to projects.projects"
        uuid cost_center_id FK
        text memo
    }
    DOCUMENT_SERIES {
        uuid id PK
        uuid company_id "cross-schema ref"
        uuid branch_id "cross-schema ref"
        enum document_type "JV|CV|CRV|CDV"
        string prefix
        bigint next_sequence "row-locked allocator (SELECT FOR UPDATE)"
        bigint series_start "BIR-registered"
        bigint series_end
        string bir_atp_no "Authority to Print / ASTRA reference"
        timestamp activated_at
        timestamp exhausted_at
    }
    TAX_CODES {
        uuid id PK
        string code UK
        string name
        decimal rate
        enum kind "vat_output|vat_input|withholding|excise"
    }
    FX_RATES {
        uuid id PK
        date rate_date
        char from_ccy "ISO 4217"
        char to_ccy "PHP"
        decimal rate "BSP reference rate"
        string source "BSP|manual"
        timestamp fetched_at
    }
    COST_CENTERS {
        uuid id PK
        uuid company_id "cross-schema ref"
        string code UK
        string name
        uuid parent_id FK "self-FK; hierarchical"
        ltree path
        bool is_active
    }
    RECURRING_TEMPLATES {
        uuid id PK
        uuid company_id "cross-schema ref"
        string name
        cron_expression schedule "e.g. '0 0 1 * *' = monthly 1st"
        jsonb template_lines
        date starts_on
        date ends_on
        timestamp last_run_at
        timestamp next_run_at
    }
```

---

## Tables (summary)

| Table | Rows (est.) | Critical indexes | Notes |
|---|---|---|---|
| `fiscal_years` | ~10 | PK, UK(company_id, year_number) | One per fiscal year; `closed_at` for year-end close |
| `fiscal_periods` | 12 × years | PK, UK(fiscal_year_id, period_number), idx(locked_at) | Monthly periods; `locked_at` triggers immutability |
| `accounts` | 200–2,000 | PK, UK(company_id, code), GiST(path) | PFRS-aligned hierarchical CoA |
| `journal_entries` | ~10k/month | PK, UK(doc_no), idx(fiscal_period_id, entry_date), idx(source, source_doc_id) | Partition by RANGE(entry_date) yearly when > 1M rows |
| `journal_lines` | ~30k/month | PK, idx(journal_entry_id), idx(account_id, entry_date) | Partition by RANGE(created_at) monthly when > 10M rows |
| `document_series` | ~10 | PK, UK(company_id, branch_id, document_type, prefix) | Row-locked allocator |
| `tax_codes` | ~30 | PK, UK(code) | Seeded from BIR; versioned via effectivity dates |
| `fx_rates` | daily, ~10 currencies | PK, UK(rate_date, from_ccy, to_ccy), idx(rate_date desc) | BSP scraper job populates daily |
| `cost_centers` | ~50 | PK, UK(company_id, code), GiST(path) | Optional dimensional analysis |
| `recurring_templates` | ~50 | PK, idx(next_run_at) | Scheduler picks up due templates |

---

## Triggers (BIR-critical)

### 1. Reject writes to locked periods
```sql
CREATE OR REPLACE FUNCTION accounting.reject_locked_period_writes() RETURNS trigger AS $$
DECLARE _locked_at timestamptz;
BEGIN
    SELECT fp.locked_at INTO _locked_at
      FROM accounting.fiscal_periods fp
     WHERE fp.id = NEW.fiscal_period_id;
    IF _locked_at IS NOT NULL THEN
        RAISE EXCEPTION 'Fiscal period % is locked since %', NEW.fiscal_period_id, _locked_at
            USING ERRCODE = 'P0001';
    END IF;
    RETURN NEW;
END $$ LANGUAGE plpgsql;

CREATE TRIGGER reject_locked_journal_writes
    BEFORE INSERT OR UPDATE ON accounting.journal_entries
    FOR EACH ROW EXECUTE FUNCTION accounting.reject_locked_period_writes();
```

### 2. Double-entry balance enforcement (deferred)
```sql
ALTER TABLE accounting.journal_lines
    ADD CONSTRAINT lines_balance_per_entry CHECK (
        debit >= 0 AND credit >= 0 AND NOT (debit > 0 AND credit > 0)
    );

-- Per-entry SUM check via deferred constraint trigger, evaluated at COMMIT
CREATE CONSTRAINT TRIGGER journal_balance_check
    AFTER INSERT OR UPDATE OR DELETE ON accounting.journal_lines
    DEFERRABLE INITIALLY DEFERRED
    FOR EACH ROW EXECUTE FUNCTION accounting.assert_balanced_entry();
```

### 3. Audit emission
```sql
CREATE TRIGGER journal_entries_audit
    AFTER INSERT OR UPDATE OF posted_at, reversed_by ON accounting.journal_entries
    FOR EACH ROW EXECUTE FUNCTION audit.write_event('JournalEntry');
```

### 4. No DELETE on posted entries
```sql
REVOKE DELETE ON accounting.journal_entries, accounting.journal_lines FROM pha_app;
-- Voiding/reversal is done via separate reversal journal entry (linked by reversed_by)
```

---

## ltree Hierarchy Example

```
1                       (Assets — root)
├─ 1.1100               (Current Assets)
│  ├─ 1.1100.01         (Cash and Cash Equivalents)
│  │  ├─ 1.1100.01.001  (Petty Cash Fund)
│  │  └─ 1.1100.01.002  (Cash in Bank — BPI)
│  └─ 1.1100.02         (Accounts Receivable)
└─ 1.1200               (Non-current Assets)
```

Query "all current assets and descendants":
```sql
SELECT * FROM accounting.accounts WHERE path <@ '1.1100';
```

---

## Cross-Schema References

**Outbound (this schema → others):**
- `journal_lines.project_id` → `projects.projects.id`
- `journal_entries.source_doc_id` → various (sales.invoices, procurement.vendor_bills, payroll.payroll_runs)

**Inbound (others → this schema):**
- `sales.invoices` → posts to `journal_entries` (1:1)
- `procurement.vendor_bills` → posts to `journal_entries` (1:1)
- `payroll.payroll_runs` → posts to `journal_entries` (1:1 per run)
- `inventory.stock_movements` → posts revaluation entries when costing changes

All references are UUID logical FKs without DB constraints across schemas.
