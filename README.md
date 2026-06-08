# Philippine Accounting System (PHA)

> Production-grade, all-in-one ERP/Accounting system designed for **full BIR compliance** (CAS Permit-to-Use + EIS-ready), built on **Laravel 11 LTS + PostgreSQL 16 + React 18**.

**Architecture:** Modular Monolith with strict DDD bounded contexts → evolving to selective microservices.
**Compliance:** Full CAS (RR 9-2009 / RR 11-2025), EIS (RR 8-2022 / RR 6-2024), e-Sales reporting (RMO 12-2013), Data Privacy Act (RA 10173), PFRS for SMEs / Full PFRS.
**Deployment:** Single-tenant Enterprise (on-premise or single-cloud).

---

## Status

🚧 **Phase 0 — Foundation in progress.**

Currently completed:
- ✅ [Architecture plan](../.claude/plans/act-as-senior-system-wild-spring.md) (locked decisions, 12–18 month roadmap)
- ✅ [Database design documentation](docs/database/) (master ERD + 11 per-schema ERDs)
- ✅ [Database bootstrap SQL](docker/postgres/init.sql) (cluster init, roles, schemas)

Next up:
- ⏳ Docker Compose setup (app, postgres, redis, minio, mailpit, horizon)
- ⏳ Laravel 11 project scaffold + DDD `app/Modules/*` structure
- ⏳ React 18 + Vite frontend scaffold
- ⏳ CI/CD pipeline (GitHub Actions)
- ⏳ Identity module (auth, RBAC, MFA)

---

## Modules (Full ERP Scope)

| Module | Status | Purpose |
|---|---|---|
| **Identity** | 🚧 designed | Companies, branches, users, roles, permissions, MFA |
| **Accounting** | 🚧 designed | CoA, GL, journals, fiscal periods, books of accounts |
| **Sales** | 🚧 designed | Customers, invoices, OR, POS, EIS-ready document numbering |
| **Inventory** | 🚧 designed | Items, warehouses, stock movements, costing |
| **Procurement** | 🚧 designed | Vendors, POs, GRNs, vendor bills, 3-way match, 2307 |
| **Payroll** | 🚧 designed | Compensation, payroll runs, SSS/PHIC/HDMF/BIR statutory |
| **HR** | 🚧 designed | Employees, departments, attendance, leave |
| **Projects** | 🚧 designed | Project accounting, timesheets, WIP, progress billings |
| **Manufacturing** | 🚧 designed | BOM, work orders, production runs, costing |
| **Tax** | 🚧 designed | BIR forms, ATC codes, 2307, 2316, alphalist, EIS gateway |
| **Reporting** | 🚧 designed | Trial Balance, BS, IS, CF, Equity, dashboards |
| **Audit** | 🚧 designed | Hash-chained event log, backup logs (CAS-mandated) |

---

## Tech Stack

| Layer | Choice | Rationale |
|---|---|---|
| Backend | Laravel 11 LTS + PHP 8.3 | LTS support, mature ecosystem, Octane for perf |
| Runtime | FrankenPHP (via Octane) | Modern HTTP server, worker mode |
| Database | PostgreSQL 16 | Schema-per-context, ltree, pgcrypto, partitioning |
| Cache / Queue | Redis 7 | Horizon-managed queues, Laravel cache, sessions |
| Storage | MinIO (S3-compatible) | On-prem friendly; AWS S3 in cloud deploys |
| Frontend | React 18 + Vite + TypeScript | SPA; not Next.js (no SSR needed for internal ERP) |
| State | TanStack Query + Zustand | Server state + minimal UI state |
| UI | shadcn/ui + Tailwind | Dense, keyboard-friendly accounting tables |
| Forms | React Hook Form + Zod | BIR forms have brutal validation rules |
| Auth | Laravel Sanctum | SPA token auth; httpOnly cookie |

---

## Controller Convention — Taylor Otwell Style

**Non-negotiable rule:** Each controller exposes only the 7 RESTful methods (`index`, `show`, `create`, `store`, `edit`, `update`, `destroy`). Every non-CRUD verb gets its own **single-action invokable controller** (`__invoke()`).

```
PostJournalEntryController       — POST /journals/{id}/post
ReverseJournalEntryController    — POST /journals/{id}/reverse
GenerateForm2550MController      — POST /tax/2550m/generate
FileForm2550MController          — POST /tax/2550m/{id}/file
IssueOfficialReceiptController   — POST /sales/{id}/issue-or
RunPayrollController             — POST /payroll/runs
TransmitInvoiceToEisController   — POST /invoices/{id}/eis
```

Controllers stay **thin** — they validate input, resolve an Action, and return a Response. Business logic lives in **Action classes** (`app/Modules/<Name>/Application/Actions/*`), one verb = one class = one public method.

---

## Database Design

The database is documented in [docs/database/](docs/database/):

- [Master ERD](docs/database/erd.md) — bird's-eye view + cross-schema relationships
- 11 per-schema ERDs covering every bounded context

**Schema strategy:** one PostgreSQL database (`accountingdb`), one schema per bounded context, no cross-schema FK constraints (cross-schema integrity via Application/Contracts). This makes future microservice extraction a `pg_dump --schema=<name>` operation.

---

## Quick Start (Phase 0)

> Once the Docker Compose setup lands, this will be a one-liner. For now, it documents the intended workflow.

### Prerequisites

- Docker Desktop 4.x or Docker Engine 24+ with Compose V2
- PHP 8.3 + Composer (only if running Laravel outside Docker)
- Node.js 20+ + npm (only for frontend dev outside Docker)

### Bootstrap

```bash
# 1. Clone
git clone https://github.com/ralmosara/PhilippineAccountingSystem.git
cd PhilippineAccountingSystem

# 2. Copy env templates
cp .env.example .env
cp frontend/.env.example frontend/.env

# 3. Bring up infrastructure
docker compose up -d postgres redis minio mailpit

# 4. Install dependencies
composer install
cd frontend && npm install && cd ..

# 5. Generate app key + run installer (creates schemas, migrates, seeds, verifies)
php artisan key:generate
php artisan pha:install

# 6. Start the app
docker compose up -d app horizon
cd frontend && npm run dev   # http://localhost:5173
```

### Verify

```bash
docker compose exec app php artisan test
docker compose exec app php artisan audit:verify-chain
docker compose exec app php artisan pha:health
```

---

## Documentation

- [Architecture Plan](../.claude/plans/act-as-senior-system-wild-spring.md) — full design with rationale
- [Database Documentation](docs/database/) — ERDs and schema reference
- `docs/architecture/` — module structure, controller conventions *(coming in Phase 0)*
- `docs/bir/` — BIR compliance posture, CAS PTU prep, EIS integration guide *(coming)*
- `docs/operations/` — deployment, backup/restore, monitoring *(coming)*

---

## Roadmap

| Phase | Duration | Deliverables |
|---|---|---|
| **0 — Foundation** | Weeks 1–3 | Repo scaffold, Docker, CI/CD, Sanctum + RBAC + MFA, audit hash-chain, base React shell |
| **1 — Core Accounting MVP** | Months 2–4 | CoA, journals, period lock, Trial Balance, books of accounts, BS/IS |
| **2 — Sales/AR + Inventory + AP** | Months 4–6 | SI/OR (sequential numbering), POS, items, stock movements, vendor bills |
| **3 — BIR Tax Engine** | Months 6–9 | VAT engine, withholding engine, all BIR forms, eBIRForms-compatible XML |
| **4 — Payroll & HR** | Months 9–11 | Payroll runs, SSS/PHIC/HDMF/BIR statutory, 13th month, 2316, 1604-CF |
| **5 — Procurement, Projects, FA, Mfg** | Months 11–13 | PO/3-way match, project P&L, fixed assets, BOM/work orders |
| **6 — EIS, e-Sales, ITR, CAS PTU** | Months 13–15 | EIS gateway live, 1701/1702, Inventory List, CAS PTU documentation pack |
| **7 — Microservices extraction** | Months 15–18 | Extract Reporting → BIR e-Filing → Notifications |

---

## Compliance Posture

| Standard / Regulation | Implementation |
|---|---|
| **BIR CAS** (RR 9-2009 / RR 11-2025) | Hash-chained audit log, sequential numbering, period locking, no-DELETE policy, books of accounts generation |
| **BIR EIS** (RR 8-2022 / RR 6-2024) | EIS gateway, X.509 invoice signing, QR codes, retry queue |
| **e-Sales Reporting** (RMO 12-2013) | Periodic XML/CSV submission to BIR |
| **TRAIN Law** (RA 10963) | Updated income tax brackets, withholding tables |
| **Data Privacy Act** (RA 10173) | Consent records, encrypted PII, breach notification workflow |
| **Labor Code & DOLE** | Statutory leaves (RA 11210, RA 8187, RA 8972), 13th month (PD 851) |
| **PFRS for SMEs / Full PFRS** | Configurable Chart of Accounts, financial statements, revenue recognition (PFRS 15) |
| **SSS / PhilHealth / Pag-IBIG** | Auto-computed contributions per current rate tables; R-3, RF-1, MCRF generation |

---

## License

TBD by repository owner.

---

## Contributors

- Project owner: ralmosara
- Architecture & implementation: built with [Claude Code](https://claude.com/claude-code) (Senior System Architect + CPA-domain pair)
