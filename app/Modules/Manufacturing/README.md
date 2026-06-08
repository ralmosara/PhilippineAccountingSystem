# Manufacturing Module

Bounded context for production/manufacturing operations. Manages Bills of Materials (BOMs),
Work Orders, and Production Runs including full inventory deduction and double-entry journal
posting on completion.

---

## Overview

The Manufacturing module follows the same DDD structure as every other PHA bounded context:

```
app/Modules/Manufacturing/
├── Domain/
│   ├── Entities/         — BillOfMaterials, BomLine, WorkOrder, ProductionRunLine
│   ├── Events/           — WorkOrderCompleted, ProductionRunPosted
│   └── ValueObjects/     — BomId, WorkOrderId, WorkOrderStatus (backed enum)
├── Application/
│   ├── Actions/          — CreateBillOfMaterials, CreateWorkOrder, StartWorkOrder,
│   │                         CompleteProductionRun, CancelWorkOrder
│   └── Contracts/        — BomRepositoryContract, WorkOrderRepositoryContract
├── Infrastructure/
│   ├── Persistence/      — EloquentBomRepository, EloquentWorkOrderRepository
│   │   └── Eloquent/     — BomModel, BomLineModel, WorkOrderModel, ProductionRunLineModel
│   └── Providers/        — ManufacturingServiceProvider
└── Presentation/
    └── Http/
        ├── Controllers/  — BomController, WorkOrderController,
        │                    StartWorkOrderController, CompleteProductionRunController,
        │                    CancelWorkOrderController
        ├── Requests/     — StoreBomRequest, StoreWorkOrderRequest,
        │                    CompleteProductionRunRequest
        └── Resources/    — BomResource, WorkOrderResource
```

---

## BOM Structure

A **Bill of Materials** defines the ingredients and costs for one batch of a finished good:

| Field | Description |
|---|---|
| `code` | Company-unique code (e.g. `BOM-FG-001`) |
| `item_id` | Finished good (logical ref to `inventory.items`) |
| `standard_batch_size` | How many units the BOM produces per run |
| `labor_cost_per_batch` | Fixed labour cost for one batch |
| `overhead_cost_per_batch` | Fixed overhead for one batch |
| `status` | `draft` → `active` → `superseded` |

Each BOM has one or more **BOM Lines** (components):

| Field | Description |
|---|---|
| `component_item_id` | Raw material (logical ref to `inventory.items`) |
| `quantity_per_batch` | How much of the component one batch consumes |
| `unit_of_measure` | e.g. `kg`, `pcs`, `L` |

---

## Work Order Lifecycle

```
draft → released → in_progress → completed
                └──────────────→ cancelled
```

1. **Create** (`POST /api/v1/work-orders`): BOM lines are expanded into `production_run_lines`
   with `quantity_required = bom_line.qty_per_batch × (qty_to_produce / standard_batch_size)`.

2. **Start** (`POST /api/v1/work-orders/{id}/start`): Sets `actual_start`, moves status to
   `in_progress`. Requires MFA middleware.

3. **Complete** (`POST /api/v1/work-orders/{id}/complete`): See below.

4. **Cancel** (`POST /api/v1/work-orders/{id}/cancel`): Hard stop — no stock changes.

---

## CompleteProductionRun Flow

The `CompleteProductionRun` action runs entirely inside a single `DB::transaction`:

### Step 1 — Validate
Check work order is `in_progress` or `released`.

### Step 2 — Cost each component
For every `production_run_line`, look up the component's `moving_avg_cost` from
`inventory.items` (row-locked via `SELECT … FOR UPDATE` on `stock_balances`).
Compute `quantity_consumed = line.qty_required × (qty_produced / qty_to_produce)`.
Compute `total_cost = qty_consumed × moving_avg_unit_cost`.

### Step 3+4 — Post journal entry
Insert into `accounting.journal_entries` + `accounting.journal_lines`:

| Side | Account | Amount |
|---|---|---|
| DR | Finished Goods (or WIP if no FG account set) | total_production_cost |
| CR | Raw Materials Inventory | per-component total cost |
| CR | WIP account | labor_cost (pro-rated) |
| CR | WIP account | overhead_cost (pro-rated) |

Journal entry is posted immediately (`posted_at = now()`).

### Step 5+6 — Stock movements + balance update
- Insert `inventory.stock_movements` record per component (`movement_type = 'consumption'`,
  quantity negative).
- Insert `inventory.stock_movements` record for finished good (`movement_type = 'production'`,
  quantity positive).
- `UPDATE inventory.stock_balances` to reduce each component's quantity and value.
- Upsert `inventory.stock_balances` for the finished good (increase quantity and value,
  recalculate moving average cost).
- `UPDATE inventory.items.moving_avg_cost` for the finished good.

### Step 7 — Complete the work order
Set `quantity_produced`, `actual_end = now()`, `status = completed`, `journal_entry_id`.

### Step 8 — Audit
Write `workorder.completed` event to `audit.events` via `AuditWriterContract`.

### Step 9 — Dispatch events
- `WorkOrderCompleted` — downstream: notifications, reporting.
- `ProductionRunPosted` — downstream: accounting reconciliation.

---

## Route List

All routes are mounted under `/api/v1` by `ModuleServiceProvider`.

| Method | Path | Controller | Auth |
|---|---|---|---|
| GET | `/api/v1/boms` | `BomController@index` | sanctum |
| POST | `/api/v1/boms` | `BomController@store` | sanctum |
| GET | `/api/v1/boms/{bom}` | `BomController@show` | sanctum |
| PUT | `/api/v1/boms/{bom}` | `BomController@update` | sanctum |
| DELETE | `/api/v1/boms/{bom}` | `BomController@destroy` | sanctum |
| GET | `/api/v1/work-orders` | `WorkOrderController@index` | sanctum |
| POST | `/api/v1/work-orders` | `WorkOrderController@store` | sanctum |
| GET | `/api/v1/work-orders/{order}` | `WorkOrderController@show` | sanctum |
| PUT | `/api/v1/work-orders/{order}` | `WorkOrderController@update` | sanctum |
| DELETE | `/api/v1/work-orders/{order}` | `WorkOrderController@destroy` | sanctum |
| POST | `/api/v1/work-orders/{order}/start` | `StartWorkOrderController` | sanctum + mfa |
| POST | `/api/v1/work-orders/{order}/complete` | `CompleteProductionRunController` | sanctum + mfa |
| POST | `/api/v1/work-orders/{order}/cancel` | `CancelWorkOrderController` | sanctum |

---

## Permissions

| Key | Description |
|---|---|
| `manufacturing.boms.view` | Read BOMs and lines |
| `manufacturing.boms.create` | Create / update BOMs |
| `manufacturing.work_orders.view` | Read work orders |
| `manufacturing.work_orders.create` | Create / update work orders |
| `manufacturing.work_orders.complete` | Complete production runs (MFA required) |

Add these to `database/seeders/IdentityRolesAndPermissionsSeeder.php`.

---

## Money Conventions

All monetary values use BCMath string arithmetic throughout the domain and application layers.
No `(float)` casts. The `Money` value object from `App\Modules\Accounting\Domain\ValueObjects\Money`
is used for cost fields in `ProductionRunLine`.

Database columns:
- `NUMERIC(18,2)` for PHP-only cost fields
- `NUMERIC(14,4)` for quantities
- `NUMERIC(10,4)` for batch sizes
