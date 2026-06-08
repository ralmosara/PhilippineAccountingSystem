# Dashboard (frontend)

Landing page after login. Currently a Phase-0 shell with quick-link tiles. Will grow into the operations-summary view once we wire in:

- Cash on hand (today's balance across all bank/cash accounts)
- AR aging (30/60/90/120+) — live from `sales.invoices` − `sales.official_receipts`
- VAT due (this period, monthly + quarterly accruals)
- Upcoming filing deadlines (1701Q / 2550M / 1601-EQ / annual ITR) with countdown chips

No persistent state — pure presentation. Anything monetary uses `formatPhp()` from `@/shared/lib/money`.
