# Reporting (frontend)

Financial-statement dashboards. Backend: [app/Modules/Reporting](../../../../app/Modules/Reporting).

## Page

**ReportingDashboardPage** — tabbed UI for the four PFRS-aligned statements:

| Tab | Period type | Backend route |
|---|---|---|
| Trial Balance | As-of | `POST /reports/trial-balance` |
| Balance Sheet | As-of | `POST /reports/balance-sheet` |
| Income Statement | For-the-period | `POST /reports/income-statement` |
| Cash Flow | For-the-period | `POST /reports/cash-flow` |

The date range inputs swap between "From/To" and "As of" based on the active tab.

## Layouts

- **Trial Balance** — flat table with debit/credit/signed-balance columns. Red banner if `total_debit !== total_credit` (data corruption signal).
- **Balance Sheet** — two-column Assets ⟷ Liabilities + Equity. Red banner if `total_assets !== total_liabilities + total_equity`.
- **Income Statement** — section-cascade with subtotals at each PFRS line: Revenue → Gross Profit → Operating Income → Income Before Tax → Net Income.
- **Cash Flow** — direct method, 3-section breakdown (operating / investing / financing) + opening + closing reconciliation banner.

## What's NOT here yet

Books of Accounts (General Journal, GL, Sales Book, Purchases Book, Cash Receipts/Disbursements Book) — backend has the generators but the SPA doesn't surface a download page yet. CAS PTU audit will need these visible from the UI.
