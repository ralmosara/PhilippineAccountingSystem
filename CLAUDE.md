# PHA — Claude Code project memory

This file is loaded into every Claude Code session that opens this repo. It captures the **conventions, regulatory constraints, and non-obvious decisions** that govern how this codebase is structured. Read it before making non-trivial changes.

For runnable instructions (install / migrate / serve) see [README.md](README.md). This file is about *what to do* and *why*.

---

## What this is

**Philippine Accounting System (PHA)** — a single-tenant, on-prem-friendly ERP/Accounting system for one Philippine company, fully aligned with BIR (Bureau of Internal Revenue) compliance:

- **CAS** (Computerized Accounting System, RR 9-2009 + RR 11-2025)
- **EIS** (e-Invoicing System, RR 8-2022 + RR 6-2024)
- **e-Sales**, TRAIN Law (RA 10963), CREATE Act (RA 11534)
- **PFRS for SMEs** (default; full PFRS is opt-in)

Stack: **Laravel 11 LTS · PHP 8.3 · PostgreSQL 16 · React 18 + Vite · Redis 7 · MinIO/S3 · Sanctum SPA auth**. FrankenPHP via Octane in production.

Architecture: **Modular Monolith with DDD bounded contexts.** Extraction to microservices is a Phase 7 concern; the seams are designed to make `pg_dump --schema=tax` a viable cut line.

---

## Non-negotiable conventions

### 1. Controllers follow the Taylor Otwell pattern

> A resource controller exposes **only the seven RESTful methods**: `index`, `show`, `create`, `store`, `edit`, `update`, `destroy`. Anything else is a **new single-action invokable controller**.

This is enforced by [tests/Architecture/ModuleBoundariesTest.php](tests/Architecture/ModuleBoundariesTest.php). For a non-CRUD verb (post a journal, void an invoice, file a form, transmit to EIS, supersede an election, etc.) you create `<Verb><Resource>Controller` with a single `__invoke()` method.

Examples that exist today: `PostJournalEntryController`, `ReverseJournalEntryController`, `LockFiscalPeriodController`, `TransmitInvoiceToEisController`, `SupersedeOsdElectionController`, `BulkImportForm2307ReceivedController`. Don't add public methods to existing controllers — create another.

Controllers stay **thin**: validate via `FormRequest` → resolve an Action from the container → return `JsonResponse`. No business logic, no Eloquent queries, no BCMath. All of that lives in **`Application/Actions/`**.

### 2. Money is BCMath-backed, never float

Every monetary value in the domain layer is a numeric string handled via `App\Modules\Accounting\Domain\ValueObjects\Money` (4 decimal scale internally, 2-decimal `toPhp()` rendering).

- **Domain code:** `Money` value objects only. Never `(float) $amount`.
- **Persistence:** `decimal(18,4)` for foreign currency, `decimal(18,2)` for PHP. Eloquent `decimal:N` cast.
- **JSON / serialisation:** decimal strings (`"1234.5678"`), never JSON numbers. The EIS [JsonCanonicalizer](app/Modules/Tax/Domain/Services/Eis/JsonCanonicalizer.php) explicitly *throws* on floats in signed payloads.
- **Browser:** scaled BigInt via [frontend/src/shared/lib/bcmath.ts](frontend/src/shared/lib/bcmath.ts). The JV editor's "are debits = credits?" check compares BigInts, not Numbers.

A single off-by-one centavo on Form 2550M can void the company's CAS Permit-to-Use. Treat float arithmetic on money as a compile error.

### 3. Module boundaries are enforced at the DB AND code level

The 12 bounded contexts each live in their own:

- **PHP namespace:** `App\Modules\<Name>\{Domain,Application,Infrastructure,Presentation}`
- **PostgreSQL schema:** `identity`, `accounting`, `sales`, `inventory`, `procurement`, `payroll`, `hr`, `projects`, `manufacturing`, `tax`, `reporting`, `audit`

Rules:

- **Domain/** never imports Laravel, Eloquent, or HTTP. Pure PHP + your own VOs. Architecture test enforces this.
- **Application/** never imports `Infrastructure/` (own or other modules'). Only `Contracts/` may cross module boundaries.
- **Cross-module DB joins are forbidden.** If you need data from another module, depend on its `Application/Contracts/` interface, implemented by that module's `Infrastructure/Persistence/`. Examples: `SellerProfileProviderContract`, `BooksOfAccountsAggregatorContract`, `OsdElectionRepositoryContract`.
- **Single FK constraints across schemas are also forbidden.** Use logical UUID references and traverse through the contract.

When in doubt: would extracting this module as `pg_dump --schema=tax` still leave the rest of the system runnable? If no, the boundary is wrong.

### 4. Audit is append-only, hash-chained

`audit.events` is a SHA-256 hash chain enforced by Postgres trigger ([audit migration](database/migrations/2026_05_10_000010_audit__create_events_table.php)). Updates and deletes are revoked at the role level. **Never write to `audit.events` directly** — go through [`AuditWriterContract`](app/Modules/Audit/Application/Contracts/AuditWriterContract.php), which sets `prev_hash`/`current_hash` and runs in the caller's transaction.

Every state-changing Action emits an event: `journalentry.posted`, `invoice.voided`, `osdelection.locked`, `form2307received.recorded`, `eissubmission.acknowledged`, etc. A daily verifier job walks the chain and pages on hash mismatch.

### 5. Sequential numbering, period locking, no DELETE on financial tables

- BIR-numbered docs (OR, SI, JV, CV, CRV, PO) draw their `doc_no` via `SELECT … FOR UPDATE` on `*.document_series`. Voids keep their number with `voided_at` set.
- `accounting.fiscal_periods.locked_at` is enforced by a Postgres trigger that rejects writes to journal_entries within a locked period.
- Posted journal entries are **immutable** — there's a trigger that rejects UPDATE on `journal_entries` once `posted_at IS NOT NULL`. Use `ReverseJournalEntry` instead.
- `REVOKE DELETE` is applied at migration 999 on `accounting.*`, `sales.*`, `tax.*`. The `pha_app` role can only soft-void.

---

## BIR compliance: what's already wired

| Concern | Where it lives |
|---|---|
| **CAS audit trail** | `audit.events` hash chain + spatie/activitylog |
| **EIS transmission** | Real PKCS#7 detached signer ([Pkcs7DetachedSigner](app/Modules/Tax/Infrastructure/Eis/Pkcs7DetachedSigner.php)) + HTTP gateway with token auth + QR generation. Bound to a `FakeEisGatewayClient` when `BIR_EIS_ENABLED=false` so dev exercises the whole pipeline without hitting BIR. |
| **VAT engine** | 12% standard / 0% zero-rated / exempt / senior+PWD (RA 9994/RA 10754) / 5% government withheld |
| **Withholding tax (issued)** | `tax.form_2307` + per-vendor PDFs, fed quarterly into SAWT |
| **Withholding tax (received)** | `tax.form_2307_received` — customer-issued 2307s become tax credits on 1701/1702 line 22. CSV bulk import + per-row error report. |
| **Quarterly ITRs** | 1701Q (May-15 / Aug-15 / Nov-15) and 1702Q (60d after each Q-end) with cumulative-YTD aggregation |
| **Annual ITRs** | 1701 (TRAIN graduated + 8% flat election) and 1702-RT (CREATE Act 20%/25% + MCIT 2%) |
| **OSD election lock** | `tax.osd_elections` table — locked by the first Q-return, asserted by every subsequent filing, with a sanctioned amendment-chain (`SupersedeOsdElection`) |
| **DAT formats** | SAWT, QAP, MAP, 1604-CF, 1604-E — byte-exact strings (CRLF separators, dash-stripped TINs, 100-char name truncation) |
| **Books of Accounts** | General Journal, GL, Sales Book, Purchases Book, Cash Receipts Book, Cash Disbursements Book — all RR 9-2009 compliant |
| **Financial Statements** | TB, BS, IS, CF, Equity — PFRS-section-classified |

### EIS specifics

- Payload JSON is **canonicalised before signing** ([JsonCanonicalizer](app/Modules/Tax/Domain/Services/Eis/JsonCanonicalizer.php)) — RFC 8785 JCS subset: sorted keys, no whitespace, no escaped slashes, **floats banned**.
- Signature is PKCS#7 / CMS detached, PEM-armoured.
- 4xx from BIR → permanent rejection (no retry). 5xx + network failure → transient, re-enqueued with exponential backoff (5m / 30m / 2h / 6h / 12h).
- Cancellation of a voided invoice uses a different endpoint — the issuance builder explicitly throws if you try to transmit a voided invoice.
- The `.p12` cert and its passphrase are loaded by [`P12CertificateLoader`](app/Modules/Tax/Infrastructure/Eis/P12CertificateLoader.php). The signer refuses an expired cert *before* touching OpenSSL.

### OSD election lock semantics

- One **active** election per `(company_id, fiscal_year, taxpayer_type)`, enforced by a Postgres partial unique index (`WHERE superseded_at IS NULL`).
- Established by the FIRST quarterly ITR for the year. Every subsequent quarterly + the annual asserts the regime matches.
- Amendments use [`SupersedeOsdElection`](app/Modules/Tax/Application/Actions/SupersedeOsdElection.php) — mark current superseded, insert successor with `replaces_id`, emit audit with `requires_amended_refile=true`. The action refuses same-regime no-op amendments.
- The `flat_8pct` regime is restricted to `taxpayer_type='individual'` at the entity level.

### 2307 received

- Each cert is one of: `recorded` (claimable), `claimed` (already on a filed 1701/1702), `rejected` (review or BIR audit pulled it), or `draft`.
- The annual 1701/1702 generator queries `findRecordedInPeriod()`, sums the credits, files the form, and **atomically** `markClaimed()`s the contributing certs into that form. Rerunning the generator without amending yields 0 new credit (no double-claim).
- Dedupe key is `(company, payor_tin, period_from, period_to, atc_code, certificate_no)`. Blank cert# values collide with each other (BIR allows blank certs).

---

## Testing strategy

### Layer / module mapping

| Test type | Location |
|---|---|
| Pure value object / domain service (no Laravel) | `tests/Unit/Modules/<Name>/` |
| Action class touching Eloquent or firing events | `tests/Feature/Modules/<Name>/` |
| Controller + FormRequest + Policy + DB | `tests/Feature/Modules/<Name>/` |
| Multi-module flows (sale → invoice → JV → 2550M) | `tests/Integration/` |
| Compile-time rules (namespace boundaries, no `dd()`) | `tests/Architecture/` |
| Hand-computed BIR file golden fixtures | `tests/Fixtures/Bir/` |

### Hard rules in test code

- **Money is a `string` in expectations.** `expect($x->tax_due)->toBe('22500.00')` — never compare floats.
- **Dates are absolute** — `new DateTimeImmutable('2026-05-15')`. Never `Carbon::now()` in a test.
- **UUIDs use the sentinel form** — `018f0000-0000-7000-8000-000000000010` for "test UUID #10". Grep-friendly; makes failures readable.
- **Test names read like inspector findings** — `it('rejects an issue that would drive quantity below zero')`. Not `test_apply_negative()`.

### Golden-file rule

BIR file formats (DAT, XML) are pinned **byte-exact** to fixtures in `tests/Fixtures/Bir/`. A diff is a **regulatory event**, not a code change: cite the BIR issuance (RR/RMC/RMO) in the PR, attach an eBIRForms validator screenshot, route review to `@cpa-team` via CODEOWNERS. **Never edit a golden file to make a test pass** — the test is the specification.

---

## Frontend conventions

### Stack
React 18 · TypeScript strict · Vite · TanStack Query for server state · Zustand for UI state · shadcn/Radix/Tailwind · React Hook Form + Zod for forms · TanStack Table where dense ledgers warrant it.

### Money in the browser
BCMath doesn't exist client-side, so [`frontend/src/shared/lib/bcmath.ts`](frontend/src/shared/lib/bcmath.ts) scales to BigInt at 4-decimal precision (matches the PHP `Money` VO). Use it any time you sum, compare, or render journal-entry totals. **Don't** use `Number` on a list of currency strings — sub-centavo precision loss is observable at 200+ rows.

The [`MoneyInput`](frontend/src/shared/components/MoneyInput.tsx) component does local-draft editing + BCMath-scaled normalisation on blur.

### Routing
Hash-based router in [`frontend/src/app/router.tsx`](frontend/src/app/router.tsx). Keeps URLs bookmarkable without depending on a separate routing library while the page count is small. Will graduate to `@tanstack/react-router` (file-based) once the surface grows past ~15 pages.

### Auth lifecycle
- Login flow: `/sanctum/csrf-cookie` → `POST /auth/login` → store token → `GET /auth/me` → hydrate user (with roles + permissions + MFA state).
- Page refresh: [`useBootstrapAuth`](frontend/src/modules/identity/api/auth.ts) re-validates the stored token by calling `/auth/me`; clears auth on 401.
- Bearer token is in `localStorage` (`pha-auth`) — works for both SPA and IDE-embedded preview.

### Keyboard ergonomics
Accountants live in the journal entry editor; it MUST work from the keyboard. The implementation in [`JournalEntryEditor`](frontend/src/modules/accounting/pages/JournalEntryEditor.tsx) wires: Tab cycles cells, Enter on last cell adds a row, Ctrl+S saves, Alt+P posts (if balanced). New high-density forms should follow the same conventions.

---

## DB conventions

- **UUIDs everywhere** — all PKs are `uuid` (v4 or v7). Cross-schema references are `uuid` columns *without* FK constraints (see boundary rule above).
- **`numeric(18,4)` for FX-eligible amounts, `numeric(18,2)` for PHP-only.** Never `double precision` for money.
- **`citext` for emails.** `ltree` for the Chart of Accounts hierarchy (GIST-indexed).
- **`timestamptz` (not `timestamp`)** everywhere. The app timezone is `Asia/Manila` (set in `APP_TIMEZONE`); storing with offset keeps multi-region queries honest.
- **Encrypted at column level (pgcrypto)** for PII: TIN, SSS#, PhilHealth#, Pag-IBIG#, bank account #. Encryption keys live in `BIR_*_KEY` env vars / Vault, never in the DB.

---

## Environment

Local dev expects Postgres + Redis + MinIO. The reference `.env` values that ship with this repo:

```env
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=accountingdb
DB_USERNAME=postgres
DB_PASSWORD=Mypass123
DB_SSLMODE=disable     # dev only; staging/prod use verify-full

REDIS_HOST=localhost
REDIS_PORT=6379

BIR_EIS_ENABLED=false  # flip to true once .p12 cert + bearer token are provisioned
```

The dev BIR EIS gateway is bound to [`FakeEisGatewayClient`](app/Modules/Tax/Infrastructure/Eis/FakeEisGatewayClient.php) when `BIR_EIS_ENABLED=false` — it acknowledges every submission with a deterministic ack number tied to the payload hash. Flip the env to `true` and supply real credentials and the same code path swaps in [`HttpEisGatewayClient`](app/Modules/Tax/Infrastructure/Eis/HttpEisGatewayClient.php) with zero source changes.

---

## Common pitfalls (don't make these)

- **Adding a method to a resource controller.** Make a single-action invokable controller instead.
- **Importing `App\Modules\Tax\Infrastructure\*` from outside the Tax module.** Use the `Application\Contracts\*` interface.
- **Casting a money string to `(float)`.** Even for "just a quick comparison". Use `bccomp`/`bcadd`/`bcsub` or the `Money` VO.
- **Editing a posted journal entry.** Reverse it and post a new one.
- **Deleting a `tax.osd_elections` row.** Supersede it via the action — preserves the amendment chain.
- **Re-rolling a 2307-received cert from `claimed` back to `recorded` directly.** Amend the 1701/1702 first; the entity refuses the transition.
- **Adding new env vars without also adding them to `.env.example`.** CI's env-diff job fails the PR if you forget.
- **Writing to `audit.events` directly via `DB::table()`.** The hash chain trigger will catch it, but you'll burn a CI run; just use `AuditWriterContract::writeEvent()`.
- **Storing a float in JSON destined for EIS.** The canonicaliser throws; you'll find out at signing time.

---

## "Where do I add X?"

| You want to add | Goes in |
|---|---|
| A new BIR form generator | `app/Modules/Tax/Application/Actions/GenerateForm<N>.php` + a single-action controller. Add it to the `DomPdfRenderer` view map and create the Blade template. |
| A new permission | `database/seeders/IdentityRolesAndPermissionsSeeder.php` PERMISSIONS array + ROLE_PERMISSIONS map. |
| A new RBAC-gated route | `Route::middleware('auth:sanctum')->group(...)` in the module's `routes.php` + `authorize()` check in the FormRequest using the new permission. |
| A new Action class | `app/Modules/<Module>/Application/Actions/<Verb><Noun>.php` — one public method (`execute`), constructor-injected dependencies, no `Illuminate\Http\Request` import. |
| A new domain event | `app/Modules/<Module>/Domain/Events/<Noun><Verbed>.php` (e.g. `InvoiceVoided`) — emit via `Dispatcher` from an Action. |
| A new BCMath domain service | `app/Modules/<Module>/Domain/Services/<Name>.php` — pure PHP, no facades. Unit-test it in `tests/Unit/Modules/<Module>/`. |
| A new frontend page | `frontend/src/modules/<module>/pages/<Name>Page.tsx` + a route in `frontend/src/app/router.tsx` + a nav link in the `TopNav` component. |

---

## Roadmap pointer

The 7-phase plan (Foundation → Core Accounting → AR/AP/Inventory → BIR Tax → Payroll → Procurement/Projects/Mfg → EIS/CAS PTU → Microservices Extraction) is captured in [docs/architecture/](docs/architecture/) and the [original plan markdown](docs/plan.md). Phases 0-3 of the backend are complete; the frontend has working shells for journals, ITR wizard, OSD elections, 2307-received, and reporting dashboards.

For the canonical database structure see [docs/database/erd.md](docs/database/erd.md) and the per-schema ERDs (`erd-accounting.md`, `erd-tax.md`, `erd-audit.md`, etc.) generated from the migrations.
