# Inventory Module

Bounded context: **items, warehouses, stock movements, moving-average costing, BIR Annual Inventory List.**

This module turns the `item_id` references in Sales and Procurement into real, auditable stock flows. Posting a sales invoice now automatically deducts stock; posting a vendor bill adds stock and rolls forward the moving-average cost.

---

## Layout

```
app/Modules/Inventory/
├── routes.php
├── Domain/
│   ├── ValueObjects/
│   │   ├── ItemId.php
│   │   ├── WarehouseId.php
│   │   └── MovementType.php                 ← enum: receipt|issue|transfer|adjustment|production|consumption|return
│   ├── Entities/
│   │   ├── Item.php
│   │   ├── Warehouse.php
│   │   ├── StockBalance.php                 ← computes averageUnitCost()
│   │   └── StockMovement.php
│   ├── Services/
│   │   └── MovingAverageCalculator.php      ← BIR-aligned weighted-average costing
│   ├── Events/StockMoved.php
│   └── Exceptions/{NegativeStock, ItemNotInventoried}Exception.php
├── Application/
│   ├── Contracts/
│   │   ├── ItemRepositoryContract.php
│   │   ├── WarehouseRepositoryContract.php
│   │   ├── StockBalanceRepositoryContract.php    ← findOrCreateForUpdate() row-locks
│   │   └── StockMovementRepositoryContract.php
│   ├── Actions/
│   │   ├── CreateItem.php
│   │   ├── RecordStockMovement.php              ← the workhorse; uses MA calculator
│   │   ├── AdjustStock.php                      ← reason-required manual override
│   │   └── GenerateInventoryList.php            ← BIR Annual Inventory List (RMC 57-2015)
│   └── Exceptions/ItemNotFoundException.php
├── Infrastructure/
│   ├── Persistence/
│   │   ├── Eloquent/{Item, ItemCategory, UnitOfMeasure, Warehouse, StockBalance, StockMovement}Model.php
│   │   ├── EloquentItemRepository.php
│   │   ├── EloquentWarehouseRepository.php
│   │   ├── EloquentStockBalanceRepository.php   ← SELECT … FOR UPDATE
│   │   └── EloquentStockMovementRepository.php
│   ├── Listeners/
│   │   ├── DeductStockOnInvoiceIssued.php       ← reacts to Sales' InvoiceIssued
│   │   └── ReceiveStockOnVendorBillPosted.php   ← reacts to Procurement's VendorBillPosted
│   └── Providers/InventoryServiceProvider.php
└── Presentation/Http/
    ├── Controllers/
    │   ├── ItemController.php                    ← 7 RESTful methods
    │   ├── WarehouseController.php               ← 7 RESTful methods
    │   ├── StockMovementController.php           ← index + show only (movements are immutable)
    │   ├── RecordStockMovementController.php     ← __invoke
    │   ├── AdjustStockController.php             ← __invoke (MFA)
    │   └── GenerateInventoryListController.php   ← __invoke (MFA)
    ├── Requests/{StoreItem, RecordStockMovement, AdjustStock, GenerateInventoryList}Request.php
    └── Resources/{Item, Warehouse, StockMovement}Resource.php
```

---

## Moving-average costing — the heart of the module

On a **receipt** of `qty` units at `unit_cost`:
```
new_quantity = current_qty + qty
new_value    = current_value + qty × unit_cost
new_ma_cost  = new_value / new_quantity        ← updates inventory.items.moving_avg_cost
```

On an **issue** of `qty` units:
```
cost_at_issue = current_ma_cost                ← what hits Cost of Goods Sold
new_quantity  = current_qty − qty
new_value     = current_value − qty × current_ma_cost
ma_cost       = unchanged                      ← issues never shift MA
```

Concurrency is handled by `EloquentStockBalanceRepository::findOrCreateForUpdate()` which issues
`SELECT … FOR UPDATE` on the `stock_balances` row. Concurrent movements on the same
(item × warehouse) serialize on that row — the moving-avg computation can never race.

**`costing_history`** captures a snapshot after each cost-affecting movement. BIR auditors get
an immutable trail of how the moving-average evolved over the year.

**Negative-stock guard:** a CHECK constraint on `stock_balances` rejects qty < 0 at the database
level. Operationally a `NegativeStockException` is thrown by the Moving Average Calculator
before the constraint fires, so the application layer gets a clean rollback with the
shortage details.

---

## Cross-module auto-flow

```
Sales POST /sales-invoices/issue
   ↓
[SalesInvoice posted; InvoiceIssued event fires]
   ↓
DeductStockOnInvoiceIssued listener (synchronous, same tx)
   ↓
For each invoice line with item_id:
   RecordStockMovement(type='issue', qty=line.quantity, unit_cost=current MA)
   → Deducts stock + writes audit event
```

```
Procurement POST /vendor-bills/post
   ↓
[VendorBill posted; VendorBillPosted event fires]
   ↓
ReceiveStockOnVendorBillPosted listener (synchronous, same tx)
   ↓
For each bill line with item_id:
   RecordStockMovement(type='receipt', qty=line.quantity, unit_cost=line.unit_price)
   → Adds stock + rolls moving-avg cost forward + writes audit event
```

If any movement throws (e.g., insufficient stock on a sale of an out-of-stock item), the
**entire sales/procurement transaction rolls back** — invoice never posts, no orphan JV.

This is the canonical example of how the event-driven design keeps modules independent
while guaranteeing consistency through the shared database transaction.

---

## BIR Annual Inventory List (RMC 57-2015 / RR 1-2018)

```
POST /api/v1/inventory/list/generate    body: { year: 2026, as_of_date: '2026-12-31' (optional) }
  → GenerateInventoryListController::__invoke
  → GenerateInventoryList::execute
     │
     ├─ 1. Single Postgres query aggregates beginning + receipts + issues + ending
     │     per item from stock_movements (no Eloquent N+1)
     │
     ├─ 2. Build CSV with BIR's standard columns:
     │     SKU, Name, UOM, Category, Beginning, Receipts, Issues, Ending,
     │     Moving Avg Cost, Ending Value
     │
     ├─ 3. Persist file to MinIO: bir/{co}/{year}/inventory-list/InventoryList_2026.csv
     │
     ├─ 4. Record in inventory.inventory_list_submissions (idempotent upsert by year)
     │
     └─ 5. audit.write_event('inventory.list_generated')

Filing deadline: within 30 days from end of fiscal year (Jan 30 for calendar filers).
The CSV is uploaded directly into eBIRForms as the attachment.
```

---

## Routes

```
GET    /api/v1/items                       index   (search, kind filter)
POST   /api/v1/items                       store
GET    /api/v1/items/{item}                show
PATCH  /api/v1/items/{item}                update
DELETE /api/v1/items/{item}                destroy  (soft-deactivate)

GET    /api/v1/warehouses                  index
…etc

GET    /api/v1/stock-movements             index   (filter: item, warehouse, type, date range)
GET    /api/v1/stock-movements/{movement}  show

POST   /api/v1/stock-movements/record      single-action — record any movement type
POST   /api/v1/stock/adjust                single-action — manual physical-count correction  (MFA)

POST   /api/v1/inventory/list/generate     single-action — BIR Annual Inventory List          (MFA)
```

---

## BIR rules implemented

| Rule | Where |
|---|---|
| Moving-average costing (BIR/PAS 2 acceptable) | `MovingAverageCalculator` |
| No negative stock without authorization | CHECK constraint + `NegativeStockException` |
| Immutable stock movements | `REVOKE DELETE ON inventory.stock_movements` |
| Costing history audit trail | `inventory.costing_history` snapshot per receipt |
| Annual Inventory List | `GenerateInventoryList` action + RMC 57-2015 format |
| Idempotent regeneration | `inventory_list_submissions` upsert keyed on (company, year) |

---

## What's pending (next batches)

- **FIFO costing method** — currently only moving-average. The schema's `costing_method` column supports it; calculator dispatch needed.
- **Lot / serial tracking** — `inventory.stock_lots` schema exists in the ERD; FEFO for perishables.
- **Reorder rules + auto-PO suggestion** — `inventory.item_reorder_rules` schema design.
- **Transfer between warehouses** — pair of `transfer_out` / `transfer_in` movements in one action.
- **Inventory revaluation** — periodic mark-to-NRV per PAS 2.
- **Multi-currency items** — currently PHP only.
