# Projects Schema — `projects.*`

**Purpose:** Project accounting, job costing, timesheets, billable hours, WIP recognition, project P&L.
**Use cases:** Construction, consulting, BPO, marketing agencies, professional services.
**PFRS alignment:** PFRS 15 Revenue from Contracts with Customers (percentage-of-completion or completed-contract).

---

## ERD

```mermaid
erDiagram
    PROJECTS ||--o{ PROJECT_PHASES : has
    PROJECT_PHASES ||--o{ PROJECT_TASKS : contains
    PROJECTS ||--o{ PROJECT_BUDGETS : "budgeted by line item"
    PROJECTS ||--o{ TIMESHEETS : "logs against"
    PROJECTS ||--o{ PROJECT_EXPENSES : incurs
    PROJECTS ||--o{ PROJECT_MILESTONES : "billed at"
    PROJECTS ||--o{ WIP_ENTRIES : recognizes
    PROJECT_TASKS ||--o{ TIMESHEETS : "logged against task"
    PROJECT_MILESTONES ||--o{ PROGRESS_BILLINGS : "billed via"

    PROJECTS {
        uuid id PK
        uuid company_id "cross-schema ref"
        uuid customer_id "cross-schema ref to sales.customers"
        string project_no UK "PROJ-2026-001"
        string name
        text description
        enum billing_type "fixed_price|time_and_materials|cost_plus|milestone"
        date starts_on
        date ends_on "expected"
        date actual_end "filled on completion"
        decimal contract_value
        char currency
        decimal fx_rate
        decimal budgeted_cost
        decimal budgeted_revenue
        enum revenue_recognition "percentage_of_completion|completed_contract|input_method|output_method"
        enum status "planning|active|on_hold|completed|cancelled"
        uuid project_manager_id "cross-schema ref to hr.employees"
        timestamp created_at
    }
    PROJECT_PHASES {
        uuid id PK
        uuid project_id FK
        smallint phase_no
        string name
        date starts_on
        date ends_on
        decimal weight_pct "for POC calculation; sums to 100 across phases"
        decimal completion_pct
    }
    PROJECT_TASKS {
        uuid id PK
        uuid project_phase_id FK
        smallint task_no
        string name
        decimal estimated_hours
        decimal actual_hours
        decimal billable_rate
        bool is_billable
        uuid assigned_to_employee_id "cross-schema ref"
        date due_date
        enum status "todo|in_progress|done|blocked"
    }
    PROJECT_BUDGETS {
        uuid id PK
        uuid project_id FK
        uuid expense_account_id "cross-schema ref to accounting.accounts"
        string description
        decimal budget_amount
        decimal actual_amount "rolled up from journal entries with project_id"
        decimal variance "computed"
    }
    TIMESHEETS {
        uuid id PK
        uuid employee_id "cross-schema ref"
        uuid project_id FK
        uuid project_task_id FK
        date work_date
        decimal hours
        decimal billable_rate
        bool is_billable
        decimal billable_amount "computed"
        text description
        enum status "draft|submitted|approved|rejected|billed"
        timestamp submitted_at
        uuid approved_by
        timestamp approved_at
        uuid billing_invoice_id "cross-schema ref to sales.sales_invoices when billed"
    }
    PROJECT_EXPENSES {
        uuid id PK
        uuid project_id FK
        uuid vendor_bill_id "cross-schema ref to procurement.vendor_bills (nullable)"
        uuid journal_entry_id "cross-schema ref"
        date expense_date
        string description
        decimal amount
        bool is_billable_to_customer
        bool is_reimbursable
    }
    PROJECT_MILESTONES {
        uuid id PK
        uuid project_id FK
        smallint milestone_no
        string name
        date target_date
        date achieved_date
        decimal billing_amount
        enum status "pending|achieved|billed"
    }
    PROGRESS_BILLINGS {
        uuid id PK
        uuid project_id FK
        uuid project_milestone_id FK
        uuid sales_invoice_id "cross-schema ref"
        decimal billed_amount
        decimal cumulative_billed
        decimal cumulative_recognized_revenue
        date billing_date
    }
    WIP_ENTRIES {
        uuid id PK
        uuid project_id FK
        date as_of_date
        decimal costs_incurred_to_date
        decimal estimated_total_costs
        decimal completion_pct "POC = costs_incurred / estimated_total"
        decimal recognized_revenue "POC × contract_value"
        decimal billed_to_date
        decimal wip_balance "recognized - billed (positive = unbilled WIP)"
        uuid journal_entry_id "cross-schema ref"
        timestamp created_at
    }
```

---

## Tables (summary)

| Table | Rows (est.) | Critical indexes | Notes |
|---|---|---|---|
| `projects` | ~100/year | PK, UK(project_no), idx(customer_id), idx(status) | |
| `project_phases` | ~5 per project | PK, idx(project_id, phase_no) | |
| `project_tasks` | ~50 per project | PK, idx(project_phase_id), idx(assigned_to_employee_id) | |
| `project_budgets` | ~10 per project | PK, idx(project_id, expense_account_id) | |
| `timesheets` | ~100k/year | PK, idx(employee_id, work_date), idx(project_id, work_date), idx(status) | |
| `project_expenses` | ~5k/year | PK, idx(project_id, expense_date) | |
| `project_milestones` | ~5 per project | PK, idx(project_id, milestone_no) | |
| `progress_billings` | ~5 per project | PK, idx(project_id, billing_date) | |
| `wip_entries` | monthly per active project | PK, idx(project_id, as_of_date desc) | Computed by month-end job |

---

## Revenue Recognition (PFRS 15)

### Percentage-of-Completion (default)
```
completion_pct = costs_incurred_to_date / estimated_total_costs
recognized_rev = completion_pct × contract_value
unbilled_wip   = recognized_rev - billed_to_date  (Asset)
billed_excess  = billed_to_date - recognized_rev  (Liability — Deferred Revenue)
```

Run by `RecognizeProjectRevenueController` (single-action) at month-end. Posts JV:
- DR `Construction in Progress / WIP Asset`
- CR `Project Revenue`

### Completed-Contract
Defer revenue and costs until project completion; on `actual_end` date, recognize all at once.

---

## Project P&L Report

```sql
SELECT
    p.id, p.name, p.contract_value AS revenue,
    SUM(pe.amount)                   AS direct_expenses,
    SUM(t.hours * t.billable_rate)   AS billable_labor,
    p.contract_value
        - SUM(pe.amount)
        - SUM(t.hours * COALESCE(emp.cost_rate, 0)) AS gross_profit
FROM projects.projects p
LEFT JOIN projects.project_expenses pe ON pe.project_id = p.id
LEFT JOIN projects.timesheets t        ON t.project_id = p.id
GROUP BY p.id;
```

(In production this becomes a materialized view in `reporting.*` refreshed nightly.)

---

## Cross-Schema References

**Outbound:**
- `projects.customer_id` → `sales.customers.id`
- `projects.project_manager_id` → `hr.employees.id`
- `project_budgets.expense_account_id` → `accounting.accounts.id`
- `timesheets.employee_id` → `hr.employees.id`
- `timesheets.billing_invoice_id` → `sales.sales_invoices.id`
- `project_expenses.vendor_bill_id` → `procurement.vendor_bills.id`
- `progress_billings.sales_invoice_id` → `sales.sales_invoices.id`
- `wip_entries.journal_entry_id` → `accounting.journal_entries.id`

**Inbound:**
- `accounting.journal_lines.project_id` ← every JE line can be tagged to a project
- `sales.sales_invoice_lines.project_id` ← invoice lines may be project-tagged
