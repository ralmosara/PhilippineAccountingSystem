# Inventory (frontend)

> **Status: placeholder** — backend has the items, warehouses, stock-movement, and moving-average calculator; UI pages will land in the next batch alongside the items list and stock-movement register.

## Planned pages

- **ItemsPage** — items master with on-hand quantity, moving-average unit cost
- **StockMovementsPage** — register of every receipt/issue/transfer with the resulting MA roll-forward
- **WarehousesPage** — bin/warehouse management
- **InventoryListExportPage** — BIR-required annual Inventory List submission

Backend reference: [app/Modules/Inventory](../../../../app/Modules/Inventory) — note `MovingAverageCalculator` enforces non-negative stock and zeroes value-when-quantity-zeroes (no floating residual).
