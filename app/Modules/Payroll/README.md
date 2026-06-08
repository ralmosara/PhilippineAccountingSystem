# Payroll Module

Bounded context: **compensation packages, payroll runs, payslips, statutory deductions (SSS/PhilHealth/Pag-IBIG/BIR), 1601-C & 2316 generation.**

This module produces every payroll-related BIR form and ties Philippine statutory compliance directly into the general ledger via the canonical cross-module pattern.

---

## Layout

```
app/Modules/Payroll/
├── routes.php
├── Domain/
│   ├── ValueObjects/{PayrollRunId, PayrollFrequency}.php
│   ├── Entities/{PayrollRun, Payslip, PayslipLine}.php
│   ├── Services/
│   │   ├── StatutoryDeductionCalculator.php       ← SSS / PhilHealth / Pag-IBIG
│   │   ├── TrainLawWithholdingCalculator.php      ← BIR WT per RA 10963 brackets
│   │   └── PayrollComputationService.php          ← orchestrates one payslip
│   └── Events/{PayrollRunComputed, PayrollRunApproved}.php
├── Application/
│   ├── Contracts/
│   │   ├── PayrollRunRepositoryContract.php
│   │   ├── CompensationProviderContract.php       ← reads compensation_packages + components
│   │   └── StatutoryRateProviderContract.php      ← reads {sss,phic,hdmf,bir}_rate_tables
│   ├── Actions/
│   │   ├── ComputePayrollRun.php                  ← loops employees, builds payslips
│   │   ├── ApprovePayrollRun.php                  ← posts JV via Accounting (MFA)
│   │   ├── GenerateForm1601C.php                  ← monthly comp WT return
│   │   └── GenerateForm2316.php                   ← annual cert per employee
│   └── Exceptions/{PayrollRunNotFound, PayrollAlreadyApproved}Exception.php
├── Infrastructure/
│   ├── Persistence/
│   │   ├── Eloquent/{PayrollRun, Payslip, PayslipLine, CompensationPackage}Model.php
│   │   ├── EloquentPayrollRunRepository.php
│   │   ├── EloquentCompensationProvider.php
│   │   └── EloquentStatutoryRateProvider.php      ← cached 1-day TTL
│   └── Providers/PayrollServiceProvider.php
└── Presentation/Http/
    ├── Controllers/
    │   ├── PayrollRunController.php                ← 7 RESTful (read/list)
    │   ├── ComputePayrollRunController.php         ← __invoke
    │   ├── ApprovePayrollRunController.php         ← __invoke (MFA)
    │   ├── GenerateForm1601CController.php         ← __invoke (MFA)
    │   └── GenerateForm2316Controller.php          ← __invoke (MFA)
    ├── Requests/{Compute, Approve, GenerateForm2316}Request.php
    └── Resources/PayrollRunResource.php
```

---

## End-to-end payroll flow

```
POST /api/v1/payroll-runs/compute     body: {
  payroll_period_id, period_start: '2026-05-01', period_end: '2026-05-31',
  frequency: 'monthly', run_type: 'regular'
}
  → ComputePayrollRunController::__invoke
  → ComputePayrollRun::execute
    │
    ├─ 1. StatutoryRateProvider::ratesEffectiveOn(2026-05-31, monthly)
    │     ↳ Returns the SSS brackets, PhilHealth config, Pag-IBIG config,
    │        and BIR monthly TRAIN brackets active on that date (cached 1d)
    │
    ├─ 2. EmployeeRepositoryContract::listActiveForCompany(companyId)
    │     ↳ Cross-module call — HR returns all active employees
    │
    ├─ 3. For each employee:
    │     ├─ CompensationProvider::findActive(employeeId, period_end)
    │     │  ↳ Returns {basic_monthly, taxable_allowances, nontaxable, is_mwe}
    │     │
    │     └─ PayrollComputationService::compute(input, rates)
    │        ├─ Gross = basic + allowances + premium pay
    │        ├─ Statutory (on basic): SSS (bracket lookup), PHIC (5% × clamped), HDMF (1%/2%)
    │        ├─ Taxable = basic + taxable_allowances + premium − statutory_ee
    │        ├─ Withholding = TRAIN bracket lookup (skipped for MWE — RA 9504)
    │        └─ Net = Gross − statutory_ee − WT − other_deductions
    │
    ├─ 4. PayrollRun + Payslips persisted (status='computed')
    │
    ├─ 5. audit.write_event('payrollrun.computed')   [hash-chained]
    │
    └─ 6. dispatch PayrollRunComputed event

POST /api/v1/payroll-runs/{run}/approve    body: { accounts: { ... } }    (MFA)
  → ApprovePayrollRunController::__invoke
  → ApprovePayrollRun::execute
    │
    ├─ 1. Aggregate totals across all payslips
    ├─ 2. Build journal lines:
    │     DR Salaries Expense                  (total gross)
    │     DR Employer Contributions             (sum of er)
    │     CR SSS Payable                        (ee + er)
    │     CR PhilHealth Payable
    │     CR Pag-IBIG Payable
    │     CR WT-Compensation Payable
    │     CR Salaries Payable                   (net pay)
    │
    ├─ 3. CreateJournalEntry + PostJournalEntry  [Accounting cross-module]
    │     ↳ Deferred balance constraint passes at COMMIT
    │
    ├─ 4. status='approved'; journal_entry_id linked
    │
    ├─ 5. audit.write_event('payrollrun.approved')
    │
    └─ 6. dispatch PayrollRunApproved event
       ↳ Future subscribers: StatutoryRemittanceGenerator, PayslipEmailer

POST /api/v1/payroll/forms/1601c/generate  body: { year: 2026, month: 5 }    (MFA)
  → GenerateForm1601CController::__invoke
  → GenerateForm1601C::execute
    ↳ Aggregates payroll.payslips WHERE approved_at IS NOT NULL for the month
    ↳ Produces 1601-C lines 14-19
    ↳ Saves to tax.bir_forms + renders PDF via Tax module's PdfRenderer

POST /api/v1/payroll/forms/2316/generate   body: { employee_id, year: 2025 }
→ Annual certificate per employee — aggregates a full year of payslips
   into a 2316 BirForm row + PDF. Distributed to employees by Jan 31.
```

---

## TRAIN Law withholding tax brackets (RA 10963 / RR 8-2018)

Monthly brackets (seeded by `StatutoryRatesSeeder`):

| Taxable Income       | Base Tax | Rate on Excess |
|---|---:|---:|
| ≤ ₱20,833            | 0         | 0%   |
| ₱20,834 – ₱33,332    | 0         | 15% on excess over ₱20,833 |
| ₱33,333 – ₱66,666    | ₱1,875    | 20% on excess over ₱33,333 |
| ₱66,667 – ₱166,666   | ₱8,541.80 | 25% on excess over ₱66,667 |
| ₱166,667 – ₱666,666  | ₱33,541.80| 30% on excess over ₱166,667|
| > ₱666,666           | ₱183,541.80| 35% on excess over ₱666,667|

Semi-monthly brackets are seeded too — `PayrollFrequency::from('semimonthly')`.

**Minimum Wage Earners (RA 9504)** are completely exempt from WT on basic + statutory benefits. The `is_minimum_wage_earner` flag on `compensation_packages` skips the TRAIN bracket lookup entirely.

---

## Statutory rates (2026, per seeded tables)

| Agency       | EE     | ER     | Floor / Cap |
|---|---:|---:|---|
| SSS          | bracket| bracket| MSC ₱5,000 – ₱35,000 (4.5% / 9.5% of MSC midpoint) |
| PhilHealth   | 2.5%   | 2.5%   | Salary clamped to ₱10,000 – ₱100,000 |
| Pag-IBIG     | 1% or 2%| 2%    | Salary capped at ₱10,000 (max ₱200 EE + ₱200 ER) |
| BIR (WT-Comp)| TRAIN  | n/a    | See bracket table above |

Rates are versioned via `effective_from`/`effective_to`. Update the seeder when a new circular issues; the cached `EloquentStatutoryRateProvider` invalidates on TTL expiry (1 day).

---

## Routes

```
GET    /api/v1/payroll-runs                   index
GET    /api/v1/payroll-runs/{run}             show

POST   /api/v1/payroll-runs/compute           single-action (compute)
POST   /api/v1/payroll-runs/{run}/approve     single-action (approve + post JV)   (MFA)

POST   /api/v1/payroll/forms/1601c/generate   single-action — monthly comp WT     (MFA)
POST   /api/v1/payroll/forms/2316/generate    single-action — annual per employee (MFA)
```

---

## Implemented (completed)

- **13th month pay** — `Generate13thMonthRun` action (PD 851). Sums annual BASIC lines from
  approved payslip_lines, divides by 12, applies RA 10963 ₱90k exemption. Route:
  `POST /api/v1/payroll-runs/13th-month/generate`
- **SSS R-3, PhilHealth RF-1, Pag-IBIG MCRF** — `GenerateStatutoryRemittance` action +
  per-agency controllers. CSV files upload-ready for each agency's portal.
- **Form 1604-CF** — already implemented in the Tax module at
  `POST /api/v1/tax-forms/1604cf/generate`. It reads from `payroll.payslips` via the
  `FormDataAggregator`. No Payroll-module action needed.

## What's pending (next batches)

- **Attendance + Leave** — actual time tracking; right now `days_worked` is computed as Mon-Fri count
- **Final pay calculator** for separated employees
- **Loans + payroll deductions** for SSS/HDMF salary loans
