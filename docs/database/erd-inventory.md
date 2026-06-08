# Inventory Schema — `inventory.*`

**Purpose:** Items (products, services, raw materials, FG), warehouses, stock movements, lot/serial tracking, costing.
**Costing methods:** Moving Average (default, BIR-acceptable) and FIFO. LIFO not allowed under PFRS.
**BIR alignment:** Annual Inventory List submission (RMC 57-2015), VAT input/output on goods.

---

## ERD

```mermaid
erDiagram
    ITEM_CATEGORIES ||--o{ ITEMS : classifies
    ITEMS ||--o{ ITEM_VARIANTS : "size/color/sku"
    ITEMS ||--o{ STOCK_MOVEMENTS : tracks
    ITEMS ||--o{ ITEM_BARCODES : has
    UNITS_OF_MEASURE ||--o{ ITEMS : measured_in
    UNITS_OF_MEASURE ||--o{ UOM_CONVERSIONS : has
    WAREHOUSES ||--o{ STOCK_MOVEMENTS : at
    WAREHOUSES ||--o{ STOCK_BALANCES : holds
    ITEMS ||--o{ STOCK_BALANCES : balanced_at
    STOCK_MOVEMENTS ||--o{ STOCK_LOTS : "lot/serial detail"
    ITEMS ||--o{ COSTING_HISTORY : "MA cost over time"
    ITEMS ||--o{ ITEM_REORDER_RULES : reorder_at

    ITEM_CATEGORIES {
        uuid id PK
        uuid company_id "cross-schema ref"
        string code UK
        string name
        uuid parent_id FK
        ltree path
    }
    ITEMS {
        uuid id PK
        uuid company_id "cross-schema ref"
        uuid category_id FK
        string sku UK
        string name
        text description
        enum kind "stock|service|asset|raw_material|fg|wip"
        uuid uom_id FK
        enum costing_method "moving_average|fifo|standard"
        decimal moving_avg_cost "numeric(18,4)"
        decimal standard_cost "numeric(18,4) optional"
        decimal selling_price "numeric(18,4)"
        bool is_vatable
        bool is_inventory "false for services"
        bool track_lots
        bool track_serials
        decimal weight_kg
        bool is_active
        timestamp created_at
    }
    ITEM_VARIANTS {
        uuid id PK
        uuid item_id FK
        string sku UK
        jsonb attributes "{color: red, size: M}"
        decimal price_adjustment
    }
    ITEM_BARCODES {
        uuid id PK
        uuid item_id FK
        uuid item_variant_id FK
        string barcode UK
        enum format "ean13|code128|qr"
    }
    UNITS_OF_MEASURE {
        uuid id PK
        string code UK "pc|kg|m|L|hr"
        string name
        enum category "count|weight|length|volume|time"
    }
    UOM_CONVERSIONS {
        uuid id PK
        uuid from_uom_id FK
        uuid to_uom_id FK
        decimal factor "1 box = 12 pcs => factor 12"
    }
    WAREHOUSES {
        uuid id PK
        uuid company_id "cross-schema ref"
        uuid branch_id "cross-schema ref"
        string code UK
        string name
        text address
        bool is_default
        bool is_active
    }
    STOCK_BALANCES {
        uuid id PK
        uuid item_id FK
        uuid warehouse_id FK
        decimal quantity "numeric(18,4)"
        decimal value "numeric(18,2)"
        timestamp last_movement_at
    }
    STOCK_MOVEMENTS {
        uuid id PK
        uuid item_id FK
        uuid warehouse_id FK
        enum movement_type "receipt|issue|transfer_in|transfer_out|adjustment|production|consumption|return"
        decimal quantity "negative for outbound"
        decimal unit_cost
        decimal total_cost
        uuid source_doc_id "polymorphic"
        string source_doc_type "sales_invoice|vendor_bill|production|adjustment"
        uuid project_id "cross-schema ref"
        timestamp moved_at
        uuid moved_by
        text remarks
    }
    STOCK_LOTS {
        uuid id PK
        uuid stock_movement_id FK
        string lot_no
        string serial_no
        date manufactured_on
        date expires_on
        decimal quantity
    }
    COSTING_HISTORY {
        uuid id PK
        uuid item_id FK
        timestamp effective_at
        decimal moving_avg_cost
        decimal quantity_on_hand
        decimal value_on_hand
        uuid trigger_movement_id FK
    }
    ITEM_REORDER_RULES {
        uuid id PK
        uuid item_id FK
        uuid warehouse_id FK
        decimal reorder_point
        decimal reorder_quantity
        decimal max_stock
        bool auto_create_po
    }
```

---

## Tables (summary)

| Table | Rows (est.) | Critical indexes | Notes |
|---|---|---|---|
| `item_categories` | ~50 | PK, UK(company_id, code), GiST(path) | Hierarchical; supports BIR Inventory List grouping |
| `items` | ~10k | PK, UK(sku), idx(company_id, is_active), idx(category_id) | `moving_avg_cost` updated by costing engine |
| `item_variants` | ~30k | PK, UK(sku), idx(item_id) | |
| `item_barcodes` | ~30k | PK, UK(barcode) | POS scan lookup |
| `units_of_measure` | ~30 (seeded) | PK, UK(code) | |
| `uom_conversions` | ~50 | PK | Convert qty across UoMs |
| `warehouses` | ~10 | PK, UK(code), idx(company_id) | |
| `stock_balances` | items × warehouses | PK, UK(item_id, warehouse_id) | Materialized current balance for fast lookup |
| `stock_movements` | ~100k/year | PK, idx(item_id, moved_at), idx(warehouse_id, moved_at), idx(source_doc_id, source_doc_type) | Partition by RANGE(moved_at) yearly when > 1M |
| `stock_lots` | ~10k | PK, idx(stock_movement_id), idx(lot_no), idx(expires_on) where expires_on is not null | FEFO for perishables |
| `costing_history` | ~100k/year | PK, idx(item_id, effective_at) | Audit trail for cost changes |
| `item_reorder_rules` | ~5k | PK, idx(item_id, warehouse_id) | Drives auto-PO suggestion |

---

## Costing Engine — Moving Average

On every receipt:
```
new_quantity = current_qty + receipt_qty
new_value    = current_value + (receipt_qty × receipt_unit_cost)
new_ma_cost  = new_value / new_quantity   (rounded to 4 dp)
```

On every issue:
```
issued_value = issue_qty × current_ma_cost
new_quantity = current_qty - issue_qty
new_value    = current_value - issued_value
ma_cost      = unchanged
```

Triggered atomically inside the same transaction as the `stock_movement` insert. Constraint: stock_balances.quantity ≥ 0 (no negative stock without override permission).

---

## Triggers

### 1. Stock balance maintenance
```sql
CREATE TRIGGER stock_movement_balance_update
    AFTER INSERT ON inventory.stock_movements
    FOR EACH ROW EXECUTE FUNCTION inventory.update_stock_balance();
```

### 2. Costing history snapshot
```sql
CREATE TRIGGER stock_movement_costing_history
    AFTER INSERT ON inventory.stock_movements
    FOR EACH ROW
    WHEN (NEW.movement_type IN ('receipt', 'production', 'adjustment'))
    EXECUTE FUNCTION inventory.snapshot_costing();
```

### 3. Negative stock guard
```sql
ALTER TABLE inventory.stock_balances
    ADD CONSTRAINT no_negative_stock CHECK (quantity >= 0);
-- Override via permission `inventory.allow_negative_stock`; bypasses constraint
-- in supervisor-mode using `SET LOCAL` GUC.
```

---

## BIR Inventory List (Annual)

Generated by `tax.GenerateInventoryListController` (single-action) on or before January 30:
- Per `item_categories` group → SKU, description, UoM, qty on hand, unit cost, total value
- Output: CSV + PDF, conformant to RMC 57-2015 schema
- Input: `stock_balances` snapshot at Dec 31 + `costing_history`

---

## Cross-Schema References

**Outbound:**
- `stock_movements.source_doc_id` → `sales.sales_invoices.id`, `procurement.vendor_bills.id`, `manufacturing.work_orders.id`
- `stock_movements.project_id` → `projects.projects.id`

**Inbound:**
- `sales.sales_invoice_lines.item_id` ← references items
- `procurement.vendor_bills.line.item_id` ← references items
- `manufacturing.boms.line.item_id` ← references raw materials
- `tax` reads `stock_balances` + `costing_history` for Inventory List
