# Sales (frontend)

Customer management, sales invoices, official receipts, POS terminal. Backend: [app/Modules/Sales](../../../../app/Modules/Sales).

## Pages

| Page | Purpose |
|---|---|
| **CustomersPage** | List + search by name/TIN/customer_no |
| **CustomerEditorPage** | Create/edit with class flags (VAT/Gov/Senior/PWD) that drive VAT treatment downstream |
| **SalesInvoicesPage** | SI list with date-range + status filters |
| **IssueSalesInvoicePage** | Full SI issue flow — customer auto-classification, live VAT preview, line-level VAT kind selector |
| **SalesInvoiceDetailPage** | View + Issue OR + Void (MFA) + Re-transmit to EIS (MFA), with a Payments table showing every OR booked against the invoice |
| **OfficialReceiptsPage** | Standalone OR list with payment-method filter |
| **PosTerminalPage** | Kiosk-mode counter-sale: Cart → Payment → Done in one focused flow |

## Customer auto-classification

When a senior or PWD customer is selected, every line auto-flips to `vat_exempt` + `discount_pct=20` (RA 9994 / RA 10754). Government customers trigger a live 5% withheld-VAT subtraction on the totals preview. The server still re-computes everything authoritatively on submit.

## Components

- **CustomerPicker** (`@/shared/components/CustomerPicker`) — autocomplete by name/TIN/customer_no with inline class chips and a "+ New customer" footer that jumps to the editor mid-flow.
- **IssueOfficialReceiptDialog** — partial-payment math, payment-method-aware reference field, fully-paid detection.

## API ([api/sales-invoices.ts](api/sales-invoices.ts), [api/official-receipts.ts](api/official-receipts.ts), [api/customers.ts](api/customers.ts))

All hooks invalidate the `['sales']` query key on success so the lists refresh after every mutation.
