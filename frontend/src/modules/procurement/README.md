# Procurement (frontend)

Vendor bills + WHT handling. Backend: [app/Modules/Procurement](../../../../app/Modules/Procurement).

## Pages

- **VendorBillsPage** — AP-side list, status filter, total/subtotal/VAT-input/WHT columns.
- **PostVendorBillPage** — line editor with per-line ATC code + live preview of expected withholding (server is source of truth).

## ATC-rate preview

The page ships with a static `ATC_RATE_PREVIEW` map of the most common ATCs (WC010 1%, WI010 5%, WI070 15%, etc.). Unknown codes display as 0 rather than guessing — that's the signal the operator typed an invalid ATC. The server's `tax.atc_codes` table is authoritative; the preview just catches obvious data-entry mistakes before posting.

## Side effects of posting a bill

Backend `PostVendorBill` action does, in one transaction:
1. Book the JV (DR Expense / VAT Input · CR AP / WHT Payable)
2. Issue a Form 2307 to the vendor (`tax.form_2307`)
3. Emit `Form2307Issued` event → feeds the quarterly SAWT

The UI just submits the form; refreshing the list after a post shows the new bill with status `posted` and a populated `journal_entry_id`.
