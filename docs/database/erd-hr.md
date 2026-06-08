# HR Schema — `hr.*`

**Purpose:** Employees, employment history, leave management, attendance, government IDs.
**Owned by role:** `pha_app`
**Compliance:** Labor Code (PD 442), DOLE leave rules, Data Privacy Act (RA 10173) for PII handling.

---

## ERD

```mermaid
erDiagram
    EMPLOYEES ||--o{ EMPLOYMENT_HISTORY : has
    EMPLOYEES ||--o{ EMPLOYEE_GOV_IDS : has
    EMPLOYEES ||--o{ EMPLOYEE_DEPENDENTS : has
    EMPLOYEES ||--o{ ATTENDANCE : logs
    EMPLOYEES ||--o{ LEAVE_BALANCES : "carries balances"
    EMPLOYEES ||--o{ LEAVE_REQUESTS : files
    LEAVE_TYPES ||--o{ LEAVE_BALANCES : tracks
    LEAVE_TYPES ||--o{ LEAVE_REQUESTS : "uses type"
    DEPARTMENTS ||--o{ EMPLOYEES : "current dept"
    POSITIONS ||--o{ EMPLOYEES : "current position"
    EMPLOYEES }o--o{ HOLIDAY_CALENDAR : "applies via branch/region"

    EMPLOYEES {
        uuid id PK
        uuid company_id "cross-schema ref"
        uuid branch_id "cross-schema ref"
        uuid user_id "cross-schema ref to identity.users (nullable)"
        string employee_no UK "EMP-000001"
        string tin "encrypted"
        string sss_no "encrypted"
        string philhealth_no "encrypted"
        string pagibig_no "encrypted"
        string first_name
        string middle_name
        string last_name
        string suffix
        date birth_date
        enum gender "male|female"
        enum civil_status "single|married|widowed|separated|divorced"
        string nationality
        string address_line
        string city
        string province
        string postal_code
        string mobile
        string email
        string emergency_contact_name
        string emergency_contact_phone
        date hired_on
        date regularized_on
        date separated_on
        enum separation_reason "resigned|terminated|retired|deceased|end_of_contract"
        uuid department_id FK
        uuid position_id FK
        uuid immediate_supervisor_id FK
        enum employment_status "probationary|regular|contract|project|consultant"
        bool is_active
        timestamp created_at
        timestamp updated_at
    }
    EMPLOYMENT_HISTORY {
        uuid id PK
        uuid employee_id FK
        date from_date
        date to_date
        uuid department_id FK
        uuid position_id FK
        decimal monthly_basic
        enum status "probationary|regular|contract|project"
        text remarks
    }
    EMPLOYEE_GOV_IDS {
        uuid id PK
        uuid employee_id FK
        enum id_type "passport|drivers_license|umid|prc|voter|postal"
        string id_number "encrypted"
        date issued_on
        date expires_on
        string issued_by
    }
    EMPLOYEE_DEPENDENTS {
        uuid id PK
        uuid employee_id FK
        string full_name
        date birth_date
        enum relationship "spouse|child|parent|sibling"
        bool is_qualified_dependent "for tax exemption (legacy pre-TRAIN)"
        bool is_philhealth_dependent
    }
    DEPARTMENTS {
        uuid id PK
        uuid company_id "cross-schema ref"
        string code UK
        string name
        uuid parent_id FK
        ltree path
        uuid head_employee_id FK
        bool is_active
    }
    POSITIONS {
        uuid id PK
        uuid company_id "cross-schema ref"
        string code UK
        string title
        uuid department_id FK
        text description
        decimal salary_grade_min
        decimal salary_grade_max
        bool is_active
    }
    ATTENDANCE {
        uuid id PK
        uuid employee_id FK
        date work_date
        timestamp time_in
        timestamp time_out
        timestamp lunch_out
        timestamp lunch_in
        decimal hours_worked
        decimal overtime_hours
        decimal nightdiff_hours
        decimal undertime_minutes
        decimal late_minutes
        enum status "present|absent|leave|holiday|rest_day"
        text remarks
    }
    LEAVE_TYPES {
        uuid id PK
        string code UK "VL|SL|EL|ML|PL|BL|SIL"
        string name
        smallint default_annual_credits "VL=15, SL=15, etc."
        bool is_paid
        bool requires_approval
        bool is_convertible_to_cash "VL convertible per company policy"
        bool carry_over_allowed
        smallint min_advance_filing_days
        bool requires_medical_cert "for SL > 3 days"
    }
    LEAVE_BALANCES {
        uuid id PK
        uuid employee_id FK
        uuid leave_type_id FK
        smallint year
        decimal opening_credits
        decimal earned_credits
        decimal used_credits
        decimal converted_credits
        decimal balance "computed: opening + earned - used - converted"
    }
    LEAVE_REQUESTS {
        uuid id PK
        uuid employee_id FK
        uuid leave_type_id FK
        date from_date
        date to_date
        decimal days_requested
        text reason
        enum status "pending|approved|rejected|cancelled"
        uuid approved_by
        timestamp approved_at
        text approval_remarks
        string medical_cert_path "for SL"
        timestamp filed_at
    }
    HOLIDAY_CALENDAR {
        uuid id PK
        date holiday_date
        string name
        enum type "regular|special_nonworking|special_working|local"
        string applicable_region "PH-NCR | PH-07 | etc."
        bool is_active
    }
```

---

## Tables (summary)

| Table | Rows (est.) | Critical indexes | Notes |
|---|---|---|---|
| `employees` | ~500 | PK, UK(employee_no), UK(tin), idx(company_id, is_active), idx(department_id) | All gov IDs encrypted |
| `employment_history` | ~5 per employee | PK, idx(employee_id, from_date desc) | |
| `employee_gov_ids` | ~3 per employee | PK, idx(employee_id) | All ID numbers encrypted |
| `employee_dependents` | ~3 per employee | PK, idx(employee_id) | |
| `departments` | ~20 | PK, UK(company_id, code), GiST(path) | Hierarchical |
| `positions` | ~50 | PK, UK(company_id, code) | |
| `attendance` | ~150k/year (500 emp × 365 days) | PK, UK(employee_id, work_date), idx(work_date) | Partition by RANGE(work_date) yearly when > 1M |
| `leave_types` | ~10 (seeded) | PK, UK(code) | VL, SL, EL, ML (105d RA 11210), PL, BL, SIL |
| `leave_balances` | ~5k/year (employees × types) | PK, UK(employee_id, leave_type_id, year) | Computed balance via trigger |
| `leave_requests` | ~3k/year | PK, idx(employee_id, from_date), idx(status) | |
| `holiday_calendar` | ~30/year | PK, idx(holiday_date) | Updated annually from Malacañang proclamations |

---

## DOLE Statutory Leaves (Phased Implementation)

| Leave | Code | Statute | Days | Notes |
|---|---|---|---|---|
| Service Incentive Leave | SIL | Labor Code Art. 95 | 5 | Min for employees with ≥1 year service |
| Maternity Leave | ML | RA 11210 (Expanded) | 105 | + 15 if solo parent (RA 8972) |
| Paternity Leave | PL | RA 8187 | 7 | Married, first 4 deliveries |
| Solo Parent Leave | SPL | RA 8972 | 7 | Per year |
| Magna Carta for Women Special Leave | MCW | RA 9710 | 60 | Gynecological surgery |
| VAWC Leave | VAWC | RA 9262 | 10 | Women victims |

Each is a row in `leave_types` with default credits seeded per the law; company policies can extend.

---

## Triggers

```sql
-- Maintain leave_balances on leave_request approval
CREATE TRIGGER leave_request_balance_update
    AFTER UPDATE OF status ON hr.leave_requests
    FOR EACH ROW
    WHEN (NEW.status = 'approved' AND OLD.status != 'approved')
    EXECUTE FUNCTION hr.deduct_leave_balance();

-- Audit on employment changes
CREATE TRIGGER employees_audit
    AFTER INSERT OR UPDATE OR DELETE ON hr.employees
    FOR EACH ROW EXECUTE FUNCTION audit.write_event('Employee');
```

---

## Cross-Schema References

**Outbound:**
- `employees.user_id` → `identity.users.id` (link to login account if applicable)
- `employees.branch_id` → `identity.branches.id`
- `employees.company_id` → `identity.companies.id`

**Inbound:**
- `payroll.compensation_packages.employee_id` ← payroll uses HR as source of truth
- `payroll.payslips.employee_id` ←
- `projects.timesheets.employee_id` ← time tracking
