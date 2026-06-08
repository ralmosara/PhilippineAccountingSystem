# Tax Module

Bounded context: **BIR forms generation, ATC codes, alphalist DAT export, PDF rendering, EIS gateway.**

This module is the BIR-facing endpoint of the system — every other module feeds it data, and Tax produces the returns, certificates, and DAT files that the company submits to BIR.

---

## Layout

```
app/Modules/Tax/
├── routes.php
├── README.md
├── Domain/
│   ├── ValueObjects/{BirFormId, FormPeriod}.php
│   ├── Entities/{BirForm, BirFormLine, AlphalistEntry}.php
│   ├── Services/
│   │   ├── VatReturnBuilder.php          ← 2550M/Q line computation
│   │   ├── WithholdingReturnBuilder.php  ← 1601-EQ + SAWT entries
│   │   └── DatFileFormatter.php          ← BIR fixed-format DAT exporter
│   └── Events/{BirFormGenerated, BirFormFiled}.php
├── Application/
│   ├── Contracts/
│   │   ├── BirFormRepositoryContract.php
│   │   ├── FormDataAggregatorContract.php   ← cross-schema reader (sales + procurement)
│   │   └── PdfRendererContract.php
│   ├── Actions/
│   │   ├── GenerateForm2550M.php             ← monthly VAT
│   │   ├── GenerateForm2550Q.php             ← quarterly VAT
│   │   ├── GenerateForm1601EQ.php            ← quarterly EWT + SAWT
│   │   ├── GenerateForm2307Pdf.php           ← per-cert PDF
│   │   ├── ExportSawtDat.php                 ← BIR fixed-format DAT
│   │   └── FileBirForm.php                   ← record filing reference
│   └── Exceptions/{FormNotFound, FormAlreadyFiled}Exception.php
├── Infrastructure/
│   ├── Persistence/
│   │   ├── Eloquent/{BirForm, BirFormLine, AlphalistEntry, AtcCode, TaxCode}Model.php
│   │   ├── EloquentBirFormRepository.php
│   │   ├── EloquentFormDataAggregator.php    ← reads sales.* + procurement.* + tax.form_2307
│   │   └── EloquentAtcCodeProvider.php       ← implements Procurement's contract
│   ├── Pdf/DomPdfRenderer.php                ← Phase 1 DomPDF; Phase 2 Browsershot
│   └── Providers/TaxServiceProvider.php
├── Presentation/
│   └── Http/
│       ├── Controllers/
│       │   ├── BirFormController.php          ← 7 RESTful methods (read/list)
│       │   ├── GenerateForm2550MController.php  ← __invoke (MFA)
│       │   ├── GenerateForm2550QController.php  ← __invoke (MFA)
│       │   ├── GenerateForm1601EQController.php ← __invoke (MFA)
│       │   ├── GenerateForm2307PdfController.php ← __invoke
│       │   ├── ExportSawtDatController.php       ← __invoke (MFA)
│       │   └── FileBirFormController.php         ← __invoke (MFA)
│       ├── Requests/{GenerateMonthly, GenerateQuarterly, FileBirForm}Request.php
│       └── Resources/BirFormResource.php
└── resources/views/pdf/{form-generic, form-2550, form-1601eq, form-1601c, form-2307}.blade.php
```

---

## End-to-end VAT return flow (2550M)

```
POST /api/v1/tax-forms/2550m/generate    body: { year: 2026, month: 5 }
  → GenerateForm2550MController::__invoke
  → GenerateForm2550M::execute
    │
    ├─ 1. FormDataAggregatorContract::aggregateVatReturn(companyId, period)
    │     ↳ EloquentFormDataAggregator (reads):
    │        ├─ sales.sales_invoices: SUM(vatable_sales, vat_zero, vat_exempt, vat_amount, withheld_vat)
    │        │  WHERE posted_at IS NOT NULL AND voided_at IS NULL
    │        └─ procurement.vendor_bills: SUM(vat_input, vat_input_deferred)
    │           WHERE posted_at IS NOT NULL AND voided_at IS NULL
    │
    ├─ 2. priorPeriodExcessInput(companyId, period)
    │     ↳ Looks up line "22" of the prior 2550M/Q, returns abs() if negative
    │
    ├─ 3. VatReturnBuilder::build(form, aggregates)
    │     ↳ Computes lines 1A, 1B, 2, 3, 8, 18A, 18B, 18D, 19, 21, 22, 23A, 23B, 24
    │     ↳ Sets form.taxDue
    │
    ├─ 4. BirFormRepositoryContract::save(form)
    │     ↳ INSERT/UPDATE tax.bir_forms + replaces tax.bir_form_lines
    │
    ├─ 5. PdfRendererContract::render(form)
    │     ↳ DomPdfRenderer renders Blade view → MinIO at bir/{co}/{year}/2550m/2550M_May 2026.pdf
    │
    ├─ 6. audit.write_event('birform.generated')   [hash-chained]
    │
    └─ 7. dispatch BirFormGenerated event
       ↳ subscribers may notify finance team, push to dashboard, etc.
```

## End-to-end EWT return flow (1601-EQ + SAWT)

```
POST /api/v1/tax-forms/1601eq/generate    body: { year: 2026, quarter: 2 }
  → GenerateForm1601EQController::__invoke
  → GenerateForm1601EQ::execute
    │
    ├─ 1. FormDataAggregatorContract::listForm2307ForPeriod(companyId, Q2)
    │     ↳ SELECT * FROM tax.form_2307 JOIN procurement.vendors WHERE period_from..to ∈ Q2
    │     ↳ One row per issued 2307 cert (created automatically when vendor bills posted)
    │
    ├─ 2. WithholdingReturnBuilder::build(form, form2307Rows)
    │     ↳ Aggregates total tax_withheld
    │     ↳ Adds form line "13" (total to be remitted), "17" (still due)
    │     ↳ Adds per-ATC breakdown lines B1..Bn
    │     ↳ Adds AlphalistEntry per cert (schedule='sawt') — these become SAWT DAT rows
    │
    ├─ 3. BirFormRepositoryContract::save(form)
    │     ↳ Persists form + lines + alphalist_entries
    │
    ├─ 4. PdfRendererContract::render(form)
    │
    └─ 5. audit + event

POST /api/v1/tax-forms/{form}/export-sawt
  → ExportSawtDatController::__invoke
  → ExportSawtDat::execute
    ↳ DatFileFormatter produces pipe-delimited fixed-format text
    ↳ Stored to MinIO at bir/{co}/{year}/sawt/SAWT_Q2 2026_<hash>.dat
    ↳ Path returned in response → SPA offers download to attach in eBIRForms
```

---

## Cross-schema reads — the only place Tax touches sales/procurement

The `EloquentFormDataAggregator` is the **sole bridge** between Tax and the
operational schemas. Everywhere else, Tax consumes other modules through
their published Contracts (e.g., `AtcCodeProvider`).

The aggregator runs through `pgsql_read` (read replica) when configured —
heavy form generation doesn't compete with transactional writes.

---

## PDF rendering

- **Phase 1 (now):** `DomPdfRenderer` uses `barryvdh/laravel-dompdf` with Blade templates under `resources/views/pdf/`. Clean tabular layout — fine for internal review and printout.
- **Phase 2:** swap to `BrowsershotRenderer` (Puppeteer/Chrome via spatie/browsershot) for pixel-perfect overlay on the official BIR PDF templates from the eBIRForms package. The contract stays the same; only the binding flips.

The 2307 template (`form-2307.blade.php`) is a fully-styled certificate ready for printing and signing.

---

## DAT file format

`DatFileFormatter::formatSawt()` produces:

```
H|000123456000|ABC TRADING INCORPORATED|2026-04-01|2026-06-30|3
D|111222333000|ACME OFFICE SUPPLIES CORP|WC010|2026-04-15|10000.00|100.00
D|333444555000|ATTY REYES LAW OFFICE|WI010|2026-05-20|50000.00|2500.00
D|444555666000|MAKATI OFFICE REALTY INC|WI070|2026-06-05|100000.00|5000.00
```

The format is mandated by BIR; a single field-order mismatch causes the entire DAT to be rejected. Test against BIR's published samples in `tests/Fixtures/Bir/` (Phase 7).

---

## Routes

```
GET    /api/v1/tax-forms                         index            (list/filter)
GET    /api/v1/tax-forms/{form}                  show
DELETE /api/v1/tax-forms/{form}                  destroy          (drafts only)

POST   /api/v1/tax-forms/2550m/generate              single-action  (MFA — monthly VAT)
POST   /api/v1/tax-forms/2550q/generate              single-action  (MFA — quarterly VAT)
POST   /api/v1/tax-forms/1601eq/generate             single-action  (MFA — quarterly EWT + SAWT)
POST   /api/v1/tax-forms/1702rt/generate             single-action  (MFA — annual corporate ITR)
POST   /api/v1/tax-forms/1701/generate               single-action  (MFA — annual individual ITR)
POST   /api/v1/tax-forms/1604cf/generate             single-action  (MFA — annual alphalist of employees)
POST   /api/v1/tax-forms/1604e/generate              single-action  (MFA — annual alphalist of EWT payees)
POST   /api/v1/tax-forms/2307/{form2307}/render      single-action
POST   /api/v1/tax-forms/{form}/export-sawt          single-action  (MFA — quarterly SAWT DAT for 1601-EQ)
POST   /api/v1/tax-forms/{form}/export-alphalist     single-action  (MFA — annual alphalist DAT for 1604-CF/E)
POST   /api/v1/tax-forms/{form}/file                 single-action  (MFA — record filing ref)
```

---

## Annual Income Tax Returns

### Form 1702-RT — Corporate ITR (CREATE Act, RA 11534)

```
POST /api/v1/tax-forms/1702rt/generate
  body: {
    year: 2026,
    prior_excess_credits: "12500.00",   // optional — from prior year ITR overpayment
    creditable_wt: "0.00",              // optional — sum of 2307s received from customers
    quarterly_payments: "85000.00"      // optional — Q1/Q2/Q3 ITR payments made
  }
  → GenerateForm1702RT::execute
    │
    ├─ 1. AnnualIncomeTaxAggregator: fiscal-year revenue / COGS / OpEx / other / accrued tax
    ├─ 2. totalAssetsAsOf(): cumulative assets excluding land (for MSME determination)
    ├─ 3. CorporateIncomeTaxCalculator::compute()
    │      ↳ MSME (20%) if taxable ≤ ₱5M AND assets ≤ ₱100M
    │      ↳ Regular (25%) otherwise
    │      ↳ MCIT (2% of gross income) — uses higher of Regular or MCIT
    ├─ 4. Subtract credits → tax_still_due (or overpayment)
    └─ 5. Persist BirForm + lines + PDF + audit event
```

### Form 1701 — Individual ITR (TRAIN Law, RA 10963)

```
POST /api/v1/tax-forms/1701/generate
  body: {
    year: 2026,
    elect_flat_8pct: false,             // optional — 8% flat tax election (gross sales ≤ ₱3M)
    prior_excess_credits: "0.00",
    creditable_wt: "0.00",
    quarterly_payments: "0.00"
  }
  → GenerateForm1701::execute
    │
    ├─ Same aggregator as 1702
    ├─ IndividualIncomeTaxCalculator::compute()
    │      ↳ Graduated brackets (0/15/20/25/30/35%) by default
    │      ↳ 8% flat (in lieu of graduated + percentage tax) if elected and eligible
    └─ Persist + PDF + audit
```

### CREATE Act MSME thresholds

| Criterion | Threshold |
|---|---|
| Net taxable income | ≤ ₱5,000,000 |
| Total assets (excluding land) | ≤ ₱100,000,000 |
| Rate if both satisfied | **20%** |
| Otherwise | **25%** |

### TRAIN Law annual brackets (RA 10963 §24(A)(2)(a))

| Annual Taxable Income | Base Tax | Rate on Excess |
|---|---:|---:|
| ≤ ₱250,000              | 0          | 0%   |
| ₱250,001 – ₱400,000     | 0          | 15%  |
| ₱400,001 – ₱800,000     | ₱22,500    | 20%  |
| ₱800,001 – ₱2,000,000   | ₱102,500   | 25%  |
| ₱2,000,001 – ₱8,000,000 | ₱402,500   | 30%  |
| > ₱8,000,000            | ₱2,202,500 | 35%  |

### Phase 1 limitations

- **Creditable WT from 2307s received**: aggregator returns `0.00` because we don't yet have a schema for 2307 certificates issued *to us* by customers. Callers can override via the `creditable_wt` body parameter. Full capture lands in a follow-up batch.
- **Optional Standard Deduction (OSD 40%)**: not yet wired. Currently uses itemized deductions only (the actual posted operating expense JE total).
- **Improperly Accumulated Earnings Tax (IAET)**: out of scope.
- **1702-EX (exempt)** and **1702-MX (mixed)** variants: schema supports them; actions land later.

---

## Annual Alphalists — Forms 1604-CF and 1604-E

### Form 1604-CF — Annual Information Return on Compensation (Jan 31 deadline)

```
POST /api/v1/tax-forms/1604cf/generate    body: { year: 2025 }
  → GenerateForm1604CF::execute
    │
    ├─ FormDataAggregator::annualEmployeeAlphalist()
    │   ↳ Aggregates approved-payroll-run payslips per employee for the year
    │   ↳ Joins hr.employees (with TIN decryption) + compensation_packages (for MWE flag)
    │   ↳ Returns: TIN, name, gross/taxable/non-taxable comp, SSS/PHIC/HDMF, WT total, is_mwe
    │
    ├─ Splits employees into Schedule 7.1 (MWE) vs Schedule 7.2 (non-MWE)
    ├─ Produces 6 summary lines (totals) + N alphalist_entries
    └─ Persists BirForm + PDF + audit event

POST /api/v1/tax-forms/{form}/export-alphalist
  → ExportAlphalistDat::execute
  ↳ DatFileFormatter produces pipe-delimited fixed-format text per BIR spec
  ↳ Stored at bir/<co>/<year>/1604cf/1604CF_<year>_<hash>.dat
  ↳ Upload via eBIRForms as the alphalist attachment to the 1604-CF return
```

### Form 1604-E — Annual Information Return on Expanded WT (Mar 1 deadline)

```
POST /api/v1/tax-forms/1604e/generate     body: { year: 2025 }
  → GenerateForm1604E::execute
    │
    ├─ FormDataAggregator::annualPayeeAlphalist()
    │   ↳ Pulls all tax.form_2307 issued during the year
    │   ↳ Groups by vendor × ATC; sums income payment + tax withheld
    │
    ├─ Per-ATC breakdown lines (B1, B2, ...)
    ├─ Schedule 1 alphalist entries — one row per (vendor, ATC) pair
    └─ Persists BirForm + PDF + audit event

POST /api/v1/tax-forms/{form}/export-alphalist
  → Same generic exporter; produces the 1604-E DAT attachment
```

### Filing deadlines (annual returns)

| Form | Deadline | Subject |
|---|---|---|
| **1604-CF** | January 31 (year after) | Employee compensation WT |
| **2316** | January 31 (year after) | Employee certificate (per employee) — already in Payroll module |
| **1604-E** | March 1 (year after) | Creditable EWT (vendors) |
| **1702-RT** / **1701** | April 15 (calendar filers) | Annual income tax return |

PHA now generates **all four** of these annual returns from posted accounting + payroll + 2307 data with one API call per form.

---

## Pending (for the BIR pipeline to be 100% production-ready)

- **Real EIS payload + signer** — replace the `TransmitInvoiceToEisJob` stub in Sales\Infrastructure\Jobs with X.509 signing using `tax.bir_certificates` and the BIR EIS schema.
- **Form 2307 received** tracking — schema + import flow for 2307s issued to us by customers.
- **1604-F** annual alphalist (final WT) — schema enum supports it; action lands when fringe-benefit WT scenarios are added.
- **OSD (Optional Standard Deduction)** for 1701/1702 — 40% of gross income deduction option.
- **e-Sales reporting** (RMO 12-2013) — periodic XML/CSV submission.
- **Browsershot-based pixel-perfect** PDF on the eBIRForms master templates.
- **eBIRForms XML** generator (the official `<Return>` schema for online submission to EFPS).

---

## BIR rules implemented

| Rule | Where |
|---|---|
| 2550M/Q computation per BIR line codes | `VatReturnBuilder` |
| 1601-EQ aggregation by ATC | `WithholdingReturnBuilder` |
| SAWT alphalist entries from 2307s | `WithholdingReturnBuilder` populates `AlphalistEntry`s |
| BIR DAT fixed-format text | `DatFileFormatter` (pipe-delimited, ASCII, CR-LF) |
| 2307 PDF certificate | `form-2307.blade.php` template |
| Carry-over of excess input VAT | `EloquentFormDataAggregator::priorPeriodExcessInput()` |
| **CREATE Act 25% / 20% MSME / MCIT 2%** | `CorporateIncomeTaxCalculator` |
| **TRAIN Law graduated brackets + 8% flat election** | `IndividualIncomeTaxCalculator` |
| **Annual ITR aggregation (revenue/COGS/OpEx/other)** | `EloquentAnnualIncomeTaxAggregator::fiscalYearTotals()` |
| **MSME asset threshold (excludes land)** | `EloquentAnnualIncomeTaxAggregator::totalAssetsAsOf()` |
| Idempotent regeneration | All `Generate*` actions short-circuit when status ≠ draft |
| Filed forms cannot be edited | DB enum status; `FormAlreadyFiledException` on retry |
| Audit trail of generation + filing | `audit.write_event` calls in every Action |
