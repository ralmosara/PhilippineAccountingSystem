# Reporting Module

Bounded context: **financial statements — Trial Balance, Balance Sheet, Income Statement, Cash Flow, and BIR books of accounts.**

This module is what CPAs hand to management. It pulls posted journal entries from the `accounting` schema (and other schemas via aggregator queries), shapes them per PFRS for SMEs / Full PFRS, and produces PDF + JSON payloads stored in MinIO with full audit trail.

---

## Layout

```
app/Modules/Reporting/
├── routes.php
├── Domain/
│   ├── ValueObjects/ReportPeriod.php
│   ├── Entities/
│   │   ├── TrialBalance.php + TrialBalanceLine
│   │   ├── BalanceSheet.php + FinancialStatementLine
│   │   └── IncomeStatement.php
│   └── Services/
│       ├── TrialBalanceBuilder.php          ← shapes raw rows into TrialBalance
│       ├── BalanceSheetBuilder.php          ← classifies by PFRS section
│       └── IncomeStatementBuilder.php       ← Revenue / COGS / OpEx / Other / Tax
├── Application/
│   ├── Contracts/
│   │   ├── ReportDataAggregatorContract.php ← reads accounting.* read-only
│   │   ├── ReportRepositoryContract.php
│   │   └── ReportPdfRendererContract.php
│   └── Actions/
│       ├── GenerateTrialBalance.php
│       ├── GenerateBalanceSheet.php
│       └── GenerateIncomeStatement.php
├── Infrastructure/
│   ├── Persistence/
│   │   ├── Eloquent/ReportRunModel.php
│   │   ├── EloquentReportRepository.php
│   │   └── EloquentReportDataAggregator.php  ← the only place we read accounting.* raw SQL
│   ├── Pdf/DomPdfReportRenderer.php
│   └── Providers/ReportingServiceProvider.php
├── resources/views/pdf/
│   ├── trial-balance.blade.php
│   ├── balance-sheet.blade.php
│   ├── income-statement.blade.php
│   └── generic.blade.php
└── Presentation/Http/
    ├── Controllers/
    │   ├── ReportController.php              ← 7 RESTful (read-only)
    │   ├── GenerateTrialBalanceController.php       ← __invoke
    │   ├── GenerateBalanceSheetController.php       ← __invoke
    │   └── GenerateIncomeStatementController.php    ← __invoke
    ├── Requests/{PeriodReport, AsOfReport}Request.php
    └── Resources/ReportRunResource.php
```

---

## Data flow

```
POST /api/v1/reports/balance-sheet    body: { as_of_date: '2026-05-31' }
  → GenerateBalanceSheetController::__invoke
  → GenerateBalanceSheet::execute
    │
    ├─ 1. ReportDataAggregatorContract::balancesAsOf(companyId, ReportPeriod::asOf(date))
    │     ↳ EloquentReportDataAggregator runs a single Postgres query:
    │        SELECT a.*, SUM(l.php_amount) … FROM accounting.accounts a
    │        LEFT JOIN journal_lines l … LEFT JOIN journal_entries e …
    │        WHERE a.is_postable AND e.posted_at IS NOT NULL
    │              AND e.entry_date <= ?
    │     ↳ Returns balances classified by PFRS via accounts.pfrs_classification
    │
    ├─ 2. netIncomeForFiscalYear(companyId, period)
    │     ↳ Computes revenue − expense for the fiscal year up to as_of_date
    │     ↳ Becomes the "Current Year Earnings" line in equity
    │
    ├─ 3. BalanceSheetBuilder::build(...)
    │     ↳ Splits rows into 5 sections: current/noncurrent assets,
    │        current/noncurrent liabilities, equity
    │     ↳ Adds current_year_earnings to equity
    │
    ├─ 4. DomPdfReportRenderer::render('balance_sheet', companyId, payload)
    │     ↳ Renders Blade view → MinIO at reporting/<co>/2026/balance_sheet/...pdf
    │
    ├─ 5. EloquentReportRepository::save(...)
    │     ↳ INSERT reporting.report_runs (payload, pdf_path, generated_by)
    │
    └─ 6. audit.write_event('report.balance_sheet_generated')   [hash-chained]
```

---

## PFRS compliance posture

| Standard | How we comply |
|---|---|
| **PFRS for SMEs Section 4** (Statement of Financial Position) | Balance Sheet splits Current vs Non-Current by `accounts.pfrs_classification` |
| **PFRS for SMEs Section 5** (Statement of Comprehensive Income) | Income Statement separates Revenue, COGS, Operating Expenses, Other Income/Expenses, Income Tax with explicit Gross Profit / Operating Income / Income Before Tax / Net Income subtotals |
| **PFRS for SMEs Section 6** (Statement of Changes in Equity) | Beginning + movement + ending balance per equity component, plus Net Income for period feeding Current Year Earnings |
| **PFRS for SMEs Section 7** (Statement of Cash Flows) | Direct method with type-based categorization: Operating / Investing / Financing classified by the dominant offsetting account's PFRS classification |
| **PFRS Section 32** (Events after the Reporting Period) | Reports are timestamped + persisted; later journal entries don't retroactively change a generated report (`payload` is frozen in `report_runs.payload`) |
| **Accruals basis** | We sum posted JEs — accrual-by-design (no cash-basis switch yet) |
| **Consistency** | Same builders → same shape regardless of period; account classifications driven from `pfrs_classification` seeded data |

The "Current Year Earnings" equity line is computed live from revenue − expense for the **fiscal year up to as_of_date**, exactly like a year-end close would consolidate them. After year-end close, the Accounting module's `CloseFiscalYear` action (future batch) will reclassify them as Retained Earnings.

### Cash Flow categorization heuristic

Cash flow lines are classified by inspecting the **dominant offsetting account** of each cash-touching journal entry (the line with the largest `|php_amount|` excluding the cash leg). The rules:

| Dominant offset | Category |
|---|---|
| `pfrs_classification = noncurrent_asset` (PPE, Land, Equipment) | **Investing** |
| `account_type IN (equity, contra_equity)` or `pfrs_classification = noncurrent_liability` | **Financing** |
| Everything else (revenue, expense, AR, AP, inventory, current liability) | **Operating** |

This is a pragmatic Phase 1 heuristic — production deployments should review the classification against their specific CoA and journal patterns. The CashFlowStatement entity exposes a `reconciles()` method that flags when beginning + net change ≠ ending cash (which indicates an uncategorized movement).

---

## Routes

```
GET    /api/v1/reports                      index   (filter: report_type, from, to)
GET    /api/v1/reports/{report}             show
DELETE /api/v1/reports/{report}             destroy

POST   /api/v1/reports/trial-balance        body: { from, to }
POST   /api/v1/reports/balance-sheet        body: { as_of_date }
POST   /api/v1/reports/income-statement     body: { from, to }
POST   /api/v1/reports/cash-flow            body: { from, to }
POST   /api/v1/reports/equity-statement     body: { from, to }

# BIR Books of Accounts (CAS — RR 9-2009)
POST   /api/v1/reports/books/general-journal           body: { from, to }
POST   /api/v1/reports/books/general-ledger            body: { from, to, account_id? }
POST   /api/v1/reports/books/sales-book                body: { from, to }
POST   /api/v1/reports/books/purchases-book            body: { from, to }
POST   /api/v1/reports/books/cash-receipts-book        body: { from, to }
POST   /api/v1/reports/books/cash-disbursements-book   body: { from, to }
```

## BIR Books of Accounts (RR 9-2009)

Six books are CAS-mandatory. They share a base class (`GenerateBook`) that handles
persistence + audit + render; each subclass only declares its `report_type` slug
and builds its own payload from `BooksOfAccountsAggregatorContract`.

| Book | Source | Notable shape |
|---|---|---|
| **General Journal** | `accounting.journal_entries` + lines, chronological | Header + indented lines; running totals |
| **General Ledger** | Per-account: opening + period txns + closing | Optional `account_id` filter for one-account focus |
| **Sales Book** | `sales.sales_invoices` (posted, non-voided) | Per-invoice VAT breakdown (vatable / zero / exempt / VAT / SC-PWD / withheld) |
| **Purchases Book** | `procurement.vendor_bills` (posted, non-voided) | Per-bill: subtotal / input VAT / WT ATC + amount |
| **Cash Receipts** | `sales.official_receipts` (non-voided) | Per-OR + payment-method breakdown |
| **Cash Disbursements** | `procurement.payment_vouchers` joined to bills→vendors | Per-CV + payment-method breakdown |

Cross-schema reads live in `EloquentBooksOfAccountsAggregator` — the only place
that joins `sales.*`, `procurement.*`, and `accounting.*` together. If Reporting
is ever extracted as a microservice (Phase 7 of the plan), this aggregator becomes
its single read API.

PDFs use **legal-size landscape** for GL/GJ/Sales/Purchases (long columns) and
**letter portrait** for the cash books. Every PDF is TIN-headered, period-labeled,
and notes "BIR CAS-compliant per RR 9-2009" — the daily/weekly print-out auditors
expect from a CAS-permitted system.

---

## Cross-module pattern

Reporting is the **canonical read-only consumer** of every other module's posted data. It owns only the `reporting` schema (just `report_runs`) and reads everything else through a single contract:

```
Reporting\Infrastructure\Persistence\EloquentReportDataAggregator
  └─ raw SQL on accounting.accounts + accounting.journal_lines + accounting.journal_entries
     (with date and posted_at filters)
```

No Eloquent imports from other modules, no calling other modules' Actions — Reporting is a sink, never a source. This makes it the natural candidate to extract as a separate microservice first (Phase 7 of the plan): replace the aggregator with an HTTP client to a read-replica-fronted Reporting service.

---

## Pending (next batches)

- **Cash Flow Statement** — direct or indirect method; needs categorization of operating/investing/financing JEs
- **Statement of Changes in Equity** — share capital, retained earnings, current year earnings movements
- **BIR Books of Accounts** — General Journal, General Ledger, Sales Book, Purchases Book, Cash Receipts/Disbursements Books (the BIR-required CAS outputs)
- **Materialized views** — promote trial_balance/balance_sheet to refreshable MVs once data grows
- **Drill-down endpoints** — clicking a line in the BS shows its underlying JEs
- **Comparative reports** — current vs prior period side-by-side
- **Multi-currency consolidation** — translate FX-tagged lines using fx_rates table
