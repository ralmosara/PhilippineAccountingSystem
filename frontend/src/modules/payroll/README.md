# Payroll (frontend)

Payroll runs + statutory deductions display. Backend: [app/Modules/Payroll](../../../../app/Modules/Payroll).

## Pages

- **PayrollRunsPage** — list with per-period totals (Gross / SSS / PHIC / HDMF / WHT / Net) and a "Compute new run" form for the current period.
- **PayrollRunDetailPage** — 6-tile summary + per-employee payslip table with all deductions shown as parentheses, MFA-gated **Approve & post JV** button (only visible to users with `payroll.runs.approve` on `computed` runs).

## Statutory tables

The compute action on the backend uses dated rate tables (`SSS_RATE_TABLE_VERSION`, `PHILHEALTH_RATE_VERSION`, `PAGIBIG_RATE_VERSION`, `BIR_TAX_TABLE_VERSION` from .env). The frontend just displays the resulting numbers; rate-table maintenance is a backend concern.

## Workflow

```
Compute → status='computed' (drafts visible, not yet booked)
   ↓ Approve & post JV (MFA)
Approved → status='approved', journal_entry_id populated
   ↓ FileSssRemittance / FilePhilhealthRemittance / FilePagibigRemittance / 1601-C
Paid    → status='paid'
```

## What's NOT yet in the UI

- 13th-month payroll run (separate flow)
- BIR Form 2316 issuance (annual, per employee)
- Statutory remittance file generation (SSS R-3, PhilHealth RF-1, Pag-IBIG MCRF)

These exist server-side; UI surfaces will arrive in a later batch.
