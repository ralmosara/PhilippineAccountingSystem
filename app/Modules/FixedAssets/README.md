# Fixed Assets Module

Bounded context for property, plant and equipment (PPE) management, PFRS for SMEs-aligned.

## Schema

PostgreSQL schema: `assets`

Tables:
- `assets.fixed_assets` — master asset register
- `assets.depreciation_schedules` — per-asset schedule summary
- `assets.depreciation_entries` — one row per asset per posted month

## Useful Life Defaults (PFRS for SMEs)

| Category              | Default Useful Life |
|-----------------------|---------------------|
| Land                  | 0 months (never depreciates) |
| Building              | 480 months (40 years) |
| Equipment             | 60 months (5 years) |
| Vehicle               | 60 months (5 years) |
| Furniture & Fixtures  | 60 months (5 years) |
| IT Equipment          | 36 months (3 years) |
| Leasehold Improvement | 120 months (10 years) |

These defaults pre-fill the register form and are the starting point for `useful_life_months`. The accountant may override them.

## Depreciation Formulas

### Straight-Line Method (SLM)

```
Monthly charge = (Acquisition Cost − Salvage Value) / Useful Life (months)
```

Produces a uniform charge every month until the asset reaches salvage value.

### Double-Declining Balance (DDB)

```
Rate           = 2 × (1 / Useful Life months)
Monthly charge = Rate × Book Value (at start of period)
```

Produces higher charges in early periods; the charge shrinks as book value decreases. The application caps the charge so book value never falls below salvage value.

### Land

Land has `useful_life_months = 0`. `computeMonthlyDepreciation()` always returns `Money::zero()` for land. No depreciation entries are ever created for land assets.

## Idempotency

`ComputeMonthlyDepreciation` is safe to run multiple times for the same period. It checks for an existing `depreciation_entry` row before posting; if one already exists for `(asset_id, year, month)` the asset is skipped. This is enforced both at the application level and by a `UNIQUE` constraint in the database.

## Disposal JV Structure

```
DR  Accumulated Depreciation    (full accumulated amount clears the contra account)
DR  Cash / Receivable           (proceeds)
DR  Loss on Disposal            (if proceeds < book value — debit side)
CR  Gain on Disposal            (if proceeds > book value — credit side)
CR  PPE / Asset Cost Account    (removes original acquisition cost)
```

Gain = Proceeds − Book Value (positive)  
Loss = Book Value − Proceeds (negative gain)

## Routes

| Method | Path | Controller | Guard |
|--------|------|------------|-------|
| GET    | `/api/v1/fixed-assets`                     | `FixedAssetController@index`   | `auth:sanctum` |
| POST   | `/api/v1/fixed-assets`                     | `FixedAssetController@store`   | `auth:sanctum` |
| GET    | `/api/v1/fixed-assets/{id}`                | `FixedAssetController@show`    | `auth:sanctum` |
| PUT    | `/api/v1/fixed-assets/{id}`                | `FixedAssetController@update`  | `auth:sanctum` |
| DELETE | `/api/v1/fixed-assets/{id}`                | `FixedAssetController@destroy` | `auth:sanctum` |
| POST   | `/api/v1/fixed-assets/depreciation/compute`| `ComputeMonthlyDepreciationController` | `auth:sanctum` + `mfa` |
| POST   | `/api/v1/fixed-assets/{id}/dispose`        | `DisposeAssetController`       | `auth:sanctum` + `mfa` |

## Permissions

| Key                       | Meaning |
|---------------------------|---------|
| `assets.view`             | List and view assets |
| `assets.register`         | Register, update, and dispose assets |
| `assets.depreciation.run` | Run the monthly depreciation batch |

## Module Load Order

`FixedAssets` is loaded after `Accounting` and before `Tax` in `ModuleServiceProvider::MODULE_LOAD_ORDER`.
It depends on `accounting.journal_entries` and `accounting.journal_lines` tables being present (for JV posting),
but does **not** import any `Accounting` infrastructure classes — only inserts via `DB::table()`.
