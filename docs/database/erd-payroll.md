# Payroll Schema — `payroll.*`

**Purpose:** Compensation packages, payroll runs, payslips, statutory deductions (SSS, PhilHealth, Pag-IBIG, BIR), 13th month pay, leave conversions.
**BIR alignment:** Form 1601-C (monthly compensation WT), Form 2316 (annual cert), Form 1604-CF (annual alphalist of employees).
**Statutory:** SSS R-3/R-5, PhilHealth RF-1, Pag-IBIG MCRF.

---

## ERD

```mermaid
erDiagram
    COMPENSATION_PACKAGES ||--o{ COMPENSATION_COMPONENTS : has
    PAYROLL_PERIODS ||--o{ PAYROLL_RUNS : "may run"
    PAYROLL_RUNS ||--|{ PAYSLIPS : has
    PAYSLIPS ||--|{ PAYSLIP_LINES : breakdown
    PAYSLIPS ||--o{ ATTENDANCE_SUMMARIES : aggregates
    PAYROLL_RUNS ||--o{ STATUTORY_REMITTANCES : "remits via"
    SSS_RATE_TABLE }o--|| PAYSLIP_LINES : uses
    PHILHEALTH_RATE_TABLE }o--|| PAYSLIP_LINES : uses
    PAGIBIG_RATE_TABLE }o--|| PAYSLIP_LINES : uses
    BIR_TAX_TABLE }o--|| PAYSLIP_LINES : uses
    PAYSLIPS ||--o{ FORM_2316 : "annual certificate (cross-schema)"

    COMPENSATION_PACKAGES {
        uuid id PK
        uuid employee_id "cross-schema ref to hr.employees"
        date effective_from
        date effective_to "null = current"
        decimal basic_monthly "numeric(18,2)"
        decimal basic_daily "computed: monthly / working_days"
        smallint working_days_per_month "default 22"
        smallint hours_per_day "default 8"
        decimal hourly_rate
        bool is_minimum_wage_earner "MWE = exempt from WT, RA 9504"
        timestamp created_at
    }
    COMPENSATION_COMPONENTS {
        uuid id PK
        uuid compensation_package_id FK
        enum component_type "allowance|deduction|bonus"
        string code "TRANSPORT_ALLOW|MEAL_ALLOW|RICE_SUBSIDY|LOAN_DEDUCTION..."
        string name
        decimal amount
        bool is_taxable
        bool is_subject_to_sss
        bool is_de_minimis "RR 5-2011 thresholds"
        decimal de_minimis_cap "monthly cap if applicable"
        bool recurring
        date one_time_date
    }
    PAYROLL_PERIODS {
        uuid id PK
        uuid company_id "cross-schema ref"
        enum frequency "monthly|semimonthly|biweekly|weekly"
        date period_start
        date period_end
        date pay_date
        bool is_finalized
    }
    PAYROLL_RUNS {
        uuid id PK
        uuid payroll_period_id FK
        string run_no UK "PR-2026-05-001"
        enum run_type "regular|13th_month|final_pay|adjustment"
        timestamp computed_at
        uuid computed_by
        timestamp approved_at
        uuid approved_by
        timestamp paid_at
        uuid journal_entry_id "cross-schema ref to accounting"
        enum status "draft|computed|approved|paid|reversed"
    }
    PAYSLIPS {
        uuid id PK
        uuid payroll_run_id FK
        uuid employee_id "cross-schema ref"
        decimal gross_compensation
        decimal taxable_compensation
        decimal nontaxable_compensation
        decimal sss_ee "employee share"
        decimal sss_er "employer share"
        decimal phic_ee
        decimal phic_er
        decimal hdmf_ee "Pag-IBIG employee"
        decimal hdmf_er
        decimal withholding_tax
        decimal other_deductions
        decimal net_pay
        smallint days_worked
        decimal hours_worked
        smallint leave_days_used
        decimal otp_amount "overtime pay"
        decimal nightdiff_amount "night differential"
        decimal holiday_pay
        timestamp generated_at
    }
    PAYSLIP_LINES {
        uuid id PK
        uuid payslip_id FK
        smallint line_no
        enum line_type "earning|allowance|deduction|tax|statutory|loan"
        string code
        string description
        decimal quantity "hours or days"
        decimal rate
        decimal amount "negative for deductions"
    }
    ATTENDANCE_SUMMARIES {
        uuid id PK
        uuid payslip_id FK
        smallint regular_days
        smallint absent_days
        smallint leave_with_pay_days
        smallint leave_without_pay_days
        smallint holiday_regular_days
        smallint holiday_special_days
        decimal overtime_hours
        decimal nightdiff_hours
        decimal undertime_minutes
        decimal late_minutes
    }
    STATUTORY_REMITTANCES {
        uuid id PK
        uuid payroll_run_id FK
        enum agency "SSS|PHILHEALTH|HDMF|BIR"
        date period_covered_start
        date period_covered_end
        decimal ee_total
        decimal er_total
        decimal grand_total
        date due_date
        date remitted_on
        string reference_no
        string file_path "generated PRN/MCRF/RF-1/etc."
        enum status "pending|generated|filed|paid|late"
    }
    SSS_RATE_TABLE {
        uuid id PK
        date effective_from
        date effective_to
        decimal msc_floor "monthly salary credit floor"
        decimal msc_ceiling
        jsonb brackets "[{floor:5000, ceiling:5249.99, ee:225, er:475}, ...]"
    }
    PHILHEALTH_RATE_TABLE {
        uuid id PK
        date effective_from
        date effective_to
        decimal premium_rate "5% as of 2026"
        decimal salary_floor "10000"
        decimal salary_ceiling "100000"
    }
    PAGIBIG_RATE_TABLE {
        uuid id PK
        date effective_from
        date effective_to
        decimal ee_rate_low "1% if salary <= 1500"
        decimal ee_rate_high "2% if salary > 1500"
        decimal er_rate "2%"
        decimal salary_cap "10000 cap on contributions"
    }
    BIR_TAX_TABLE {
        uuid id PK
        date effective_from
        date effective_to
        enum frequency "daily|weekly|semimonthly|monthly|annual"
        jsonb brackets "[{floor:0, ceiling:20833, base:0, rate:0}, {floor:20834, ceiling:33332, base:0, rate:0.15}, ...]"
    }
    LOANS {
        uuid id PK
        uuid employee_id "cross-schema ref"
        enum loan_type "sss_salary|sss_calamity|hdmf_calamity|hdmf_multipurpose|company"
        decimal principal
        decimal monthly_amortization
        smallint term_months
        date start_date
        decimal balance
        bool is_active
    }
    LOAN_DEDUCTIONS {
        uuid id PK
        uuid loan_id FK
        uuid payslip_id FK
        decimal amount_deducted
        decimal balance_after
    }
```

---

## Tables (summary)

| Table | Rows (est.) | Critical indexes | Notes |
|---|---|---|---|
| `compensation_packages` | ~1.5 per employee | PK, idx(employee_id, effective_from desc) | Versioned via effective dates |
| `compensation_components` | ~5 per package | PK, idx(compensation_package_id) | de minimis caps from RR 5-2011 |
| `payroll_periods` | ~24/year (semimonthly) | PK, UK(company_id, period_start, period_end) | |
| `payroll_runs` | ~24/year + bonuses | PK, UK(run_no) | |
| `payslips` | employees × periods | PK, UK(payroll_run_id, employee_id) | |
| `payslip_lines` | ~10 per payslip | PK, idx(payslip_id, line_no) | |
| `attendance_summaries` | 1 per payslip | PK, UK(payslip_id) | Aggregated from `hr.attendance` |
| `statutory_remittances` | 4 per period (SSS, PHIC, HDMF, BIR) | PK, idx(agency, period_covered_start) | |
| `sss_rate_table` | ~10 versions over time | PK, idx(effective_from desc) | Updated when SSS issues new circular |
| `philhealth_rate_table` | ~5 versions | PK, idx(effective_from desc) | |
| `pagibig_rate_table` | ~3 versions | PK, idx(effective_from desc) | |
| `bir_tax_table` | one per frequency × version | PK, UK(frequency, effective_from) | TRAIN Law brackets |
| `loans` | ~100 active | PK, idx(employee_id, is_active) | SSS/HDMF/company loans |
| `loan_deductions` | per payslip × active loan | PK, UK(loan_id, payslip_id) | |

---

## TRAIN Law Withholding Tax Computation (Compensation)

Per RA 10963 (TRAIN), updated brackets effective 2023+:

| Annual Taxable Income | Tax |
|---|---|
| ≤ ₱250,000 | 0 |
| ₱250,001 – ₱400,000 | 15% of excess over ₱250,000 |
| ₱400,001 – ₱800,000 | ₱22,500 + 20% of excess over ₱400,000 |
| ₱800,001 – ₱2,000,000 | ₱102,500 + 25% of excess over ₱800,000 |
| ₱2,000,001 – ₱8,000,000 | ₱402,500 + 30% of excess over ₱2,000,000 |
| > ₱8,000,000 | ₱2,202,500 + 35% of excess over ₱8,000,000 |

Engine: `Payroll\Domain\Services\WithholdingTaxCalculator` resolves the bracket via `bir_tax_table` and applies it per payroll frequency.

---

## SSS Computation (2025+ schedule)

Lookup `sss_rate_table.brackets` for the employee's MSC (Monthly Salary Credit). Each bracket has `ee` and `er` amounts. Total contribution = `ee + er + ec` (Employee Compensation).

## PhilHealth (2026)

`premium_rate × min(max(monthly_salary, floor), ceiling)`, split 50/50 EE/ER.
Floor: ₱10,000 → ₱500 total. Ceiling: ₱100,000 → ₱5,000 total. Rate: 5%.

## Pag-IBIG

EE: 1% if monthly salary ≤ ₱1,500, else 2%. ER: 2%. Cap: ₱10,000 base → max ₱200 EE + ₱200 ER.

---

## 13th Month Pay (PD 851)

Computed annually:
```
13th_month = SUM(basic_pay_received_during_year) / 12
```
Tax-exempt up to ₱90,000 (RR 11-2018); excess subject to WT.

Generated via dedicated `RunPayrollController` with `run_type='13th_month'` (single-action verb), separate from regular runs.

---

## Triggers

```sql
-- Audit on payroll approval (high-stakes)
CREATE TRIGGER payroll_runs_audit
    AFTER UPDATE OF status, approved_at, paid_at ON payroll.payroll_runs
    FOR EACH ROW EXECUTE FUNCTION audit.write_event('PayrollRun');

-- No DELETE on finalized payroll
REVOKE DELETE ON payroll.payroll_runs, payroll.payslips, payroll.payslip_lines FROM pha_app;
```

---

## Cross-Schema References

**Outbound:**
- `compensation_packages.employee_id` → `hr.employees.id`
- `payslips.employee_id` → `hr.employees.id`
- `payroll_runs.journal_entry_id` → `accounting.journal_entries.id`
- `attendance_summaries` reads from `hr.attendance` and `hr.leave_requests`

**Inbound:**
- `tax.form_2316.payslip_summary` ← annual aggregation
- `tax.alphalist_entries` (Schedule 7.1 Annual Alphalist of Employees) ← 1604-CF aggregation
