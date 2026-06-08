# Manufacturing Schema — `manufacturing.*`

**Purpose:** Bill of Materials (BOM), Work Orders, production runs, production costing, scrap/yield tracking.
**PFRS alignment:** PAS 2 Inventories — manufacturing costing standards.

---

## ERD

```mermaid
erDiagram
    BOMS ||--|{ BOM_LINES : has
    BOMS ||--o{ BOM_VERSIONS : "version history"
    ITEMS ||--o{ BOMS : "produced item"
    ITEMS ||--o{ BOM_LINES : "raw material"
    BOMS ||--o{ ROUTINGS : "process steps"
    ROUTINGS ||--|{ ROUTING_OPERATIONS : has
    WORK_CENTERS ||--o{ ROUTING_OPERATIONS : performed_at

    WORK_ORDERS ||--o{ PRODUCTION_RUNS : "executes via"
    WORK_ORDERS }o--|| BOMS : based_on
    PRODUCTION_RUNS ||--|{ MATERIAL_ISSUES : consumes
    PRODUCTION_RUNS ||--|{ PRODUCTION_OUTPUTS : produces
    PRODUCTION_RUNS ||--o{ LABOR_ENTRIES : tracks
    PRODUCTION_RUNS ||--o{ PRODUCTION_COSTING : "captures actual cost"

    BOMS {
        uuid id PK
        uuid company_id "cross-schema ref"
        uuid output_item_id "cross-schema ref to inventory.items (FG)"
        string bom_code UK "BOM-FG-001-v3"
        smallint version
        string name
        decimal output_quantity
        uuid output_uom_id "cross-schema ref"
        bool is_active
        date effective_from
        date effective_to
    }
    BOM_VERSIONS {
        uuid id PK
        uuid bom_id FK
        smallint version
        timestamp created_at
        uuid created_by
        text change_notes
    }
    BOM_LINES {
        uuid id PK
        uuid bom_id FK
        smallint line_no
        uuid raw_material_id "cross-schema ref to inventory.items"
        decimal quantity
        uuid uom_id "cross-schema ref"
        decimal scrap_pct "expected loss"
        bool is_optional
        text remarks
    }
    ROUTINGS {
        uuid id PK
        uuid bom_id FK
        string routing_code
        string name
    }
    ROUTING_OPERATIONS {
        uuid id PK
        uuid routing_id FK
        smallint operation_no
        uuid work_center_id FK
        string description
        decimal setup_minutes
        decimal run_minutes_per_unit
        decimal labor_rate_per_hour
        decimal overhead_rate_per_hour
    }
    WORK_CENTERS {
        uuid id PK
        uuid company_id "cross-schema ref"
        string code UK
        string name
        decimal capacity_hours_per_day
        decimal hourly_overhead_rate
    }
    WORK_ORDERS {
        uuid id PK
        uuid bom_id FK
        string wo_no UK "WO-2026-000001"
        date order_date
        date target_completion
        decimal target_quantity
        decimal completed_quantity "running total"
        decimal scrap_quantity
        enum status "draft|released|in_progress|completed|cancelled"
        uuid sales_order_id "cross-schema ref (nullable, MTO)"
        uuid project_id "cross-schema ref"
    }
    PRODUCTION_RUNS {
        uuid id PK
        uuid work_order_id FK
        date run_date
        decimal output_quantity
        decimal scrap_quantity
        timestamp started_at
        timestamp ended_at
        uuid foreman_employee_id "cross-schema ref"
    }
    MATERIAL_ISSUES {
        uuid id PK
        uuid production_run_id FK
        uuid raw_material_id "cross-schema ref to inventory.items"
        decimal issued_quantity
        decimal returned_quantity
        decimal consumed_quantity "issued - returned"
        decimal unit_cost
        decimal total_cost
        uuid stock_movement_id "cross-schema ref to inventory.stock_movements"
    }
    PRODUCTION_OUTPUTS {
        uuid id PK
        uuid production_run_id FK
        uuid output_item_id "cross-schema ref to inventory.items (FG)"
        decimal quantity
        decimal unit_cost "computed: total_costs / quantity"
        uuid stock_movement_id "cross-schema ref"
    }
    LABOR_ENTRIES {
        uuid id PK
        uuid production_run_id FK
        uuid employee_id "cross-schema ref"
        uuid routing_operation_id FK
        decimal hours
        decimal labor_cost
        decimal overhead_cost
    }
    PRODUCTION_COSTING {
        uuid id PK
        uuid production_run_id FK
        decimal total_material_cost
        decimal total_labor_cost
        decimal total_overhead_cost
        decimal total_cost "sum of all"
        decimal output_quantity
        decimal cost_per_unit
        uuid journal_entry_id "cross-schema ref"
        timestamp posted_at
    }
```

---

## Tables (summary)

| Table | Rows (est.) | Critical indexes | Notes |
|---|---|---|---|
| `boms` | ~500 | PK, UK(bom_code) | Multi-version per FG |
| `bom_versions` | ~3 per BOM | PK, idx(bom_id, version) | |
| `bom_lines` | ~10 per BOM | PK, idx(bom_id, line_no) | |
| `routings` | ~500 | PK, UK(bom_id, routing_code) | |
| `routing_operations` | ~5 per routing | PK, idx(routing_id, operation_no) | |
| `work_centers` | ~20 | PK, UK(code) | |
| `work_orders` | ~5k/year | PK, UK(wo_no), idx(bom_id, status), idx(project_id) | |
| `production_runs` | ~10k/year | PK, idx(work_order_id, run_date) | |
| `material_issues` | ~50k/year | PK, idx(production_run_id) | |
| `production_outputs` | ~10k/year | PK, idx(production_run_id) | |
| `labor_entries` | ~100k/year | PK, idx(production_run_id, employee_id) | |
| `production_costing` | 1 per run | PK, UK(production_run_id) | Posted to GL |

---

## Costing Flow

On each `production_run` completion:

```
1. Material cost   = SUM(material_issues.consumed_quantity × moving_avg_cost)
2. Labor cost      = SUM(labor_entries.hours × labor_rate_per_hour)
3. Overhead cost   = SUM(labor_entries.hours × overhead_rate_per_hour)
4. Total cost      = material + labor + overhead
5. Unit cost       = total_cost / output_quantity
```

Posted as JV:
- DR `Finished Goods Inventory` (output_quantity × unit_cost)
- DR `Manufacturing Variance` (if standard cost differs)
- CR `Raw Materials Inventory` (material cost)
- CR `Direct Labor` (labor)
- CR `Manufacturing Overhead Applied` (overhead)

`PostProductionCostingController` is a single-action invokable controller.

---

## Cross-Schema References

**Outbound:**
- `boms.output_item_id`, `bom_lines.raw_material_id` → `inventory.items.id`
- `material_issues.stock_movement_id` → `inventory.stock_movements.id`
- `production_outputs.stock_movement_id` → `inventory.stock_movements.id`
- `labor_entries.employee_id` → `hr.employees.id`
- `production_costing.journal_entry_id` → `accounting.journal_entries.id`
- `work_orders.sales_order_id` → `sales.sales_orders.id`
- `work_orders.project_id` → `projects.projects.id`

**Inbound:**
- `inventory.stock_movements` consumes raw materials, produces FG
- `accounting.journal_entries` records the costing
