# PHA Test Suite

This directory holds every automated test for the Philippine Accounting System.
Tests are written in [Pest 3](https://pestphp.com/) (PHP 8.3+) and organised by
**layer × bounded context** so that a failure points straight at the responsible
module owner.

> **Why so many tests?** A BIR-compliant CAS (Computerized Accounting System) is
> not a normal CRUD app — a single off-by-one centavo on Form 2550M, or a
> missing sequence number on an Official Receipt, can void the company's CAS
> Permit-to-Use. Every domain rule below is **either pinned by a test or treated
> as untrusted**.

---

## Layout

```
tests/
├── Pest.php                           # global expectations + helpers
├── TestCase.php                       # base case (DB transactions, faker locale en_PH)
│
├── Architecture/                      # PHPStan-style structural rules
│   └── ModuleBoundariesTest.php       # forbids cross-module imports
│
├── Unit/                              # pure domain — no DB, no HTTP, no I/O
│   └── Modules/
│       ├── Accounting/
│       │   ├── MoneyTest.php                       # BCMath value object
│       │   └── DoubleEntryValidatorTest.php        # debits == credits invariant
│       ├── Inventory/
│       │   └── MovingAverageCalculatorTest.php     # MA cost roll-forward
│       ├── Payroll/
│       │   ├── TrainLawWithholdingCalculatorTest.php
│       │   └── StatutoryDeductionCalculatorTest.php   # SSS / PHIC / HDMF
│       ├── Procurement/
│       │   ├── WithholdingTaxCalculatorTest.php       # ATC codes (WI/WC/WV…)
│       │   └── ThreeWayMatcherTest.php                # PO ⟷ GRN ⟷ Bill
│       ├── Reporting/
│       │   ├── TrialBalanceBuilderTest.php
│       │   ├── IncomeStatementBuilderTest.php         # PFRS sections
│       │   └── CashFlowBuilderTest.php                # PAS 7 direct method
│       ├── Sales/
│       │   └── VatCalculatorTest.php                  # 12% / 0% / exempt / senior
│       └── Tax/
│           ├── CorporateIncomeTaxCalculatorTest.php   # CREATE Act / MCIT
│           ├── IndividualIncomeTaxCalculatorTest.php  # TRAIN graduated + 8%
│           └── DatFileFormatterTest.php               # BIR DAT byte-format
│
├── Feature/                           # HTTP layer — routes, FormRequests, Policies
│   └── Modules/<Name>/…
│
├── Integration/                       # multi-module flows (DB + queue)
│   └── …
│
└── Fixtures/
    ├── Bir/                           # golden files: hand-computed BIR outputs
    │   ├── 2550M-2026-04-acme.xml
    │   ├── 2307-roberto-garcia.pdf
    │   └── alphalist-2025-1604-cf.dat
    └── ChartOfAccounts/
        └── pfrs-sme-default.json
```

### Where does my test go?

| Type of code under test                                  | Folder         |
| -------------------------------------------------------- | -------------- |
| Pure value object / domain service (no Laravel)          | `Unit/`        |
| Action class that touches Eloquent or fires events       | `Feature/`     |
| Controller + FormRequest + Policy + DB                   | `Feature/`     |
| "Sale → Journal → VAT → 2550M" cross-module journey      | `Integration/` |
| Compile-time rule (namespace boundaries, no `dd()`, …)   | `Architecture/`|

---

## Running

```bash
# Full suite (matches CI)
vendor/bin/pest --parallel

# Single module (fast feedback while developing)
vendor/bin/pest tests/Unit/Modules/Tax

# Single test by description (Pest's --filter is a substring match)
vendor/bin/pest --filter='8% flat tax'

# With coverage (target: 90% on Domain + Application layers)
vendor/bin/pest --coverage --min=90

# Architecture rules only (sub-second)
vendor/bin/pest tests/Architecture
```

CI runs `--parallel --coverage --min=90` and **fails** on any uncovered line in
`app/Modules/*/Domain/` or `app/Modules/*/Application/Actions/`. Infrastructure
and Presentation layers are exempt from the coverage threshold but still subject
to feature tests.

---

## Conventions

### 1. Money is always a `string` in tests

```php
expect($result['tax_due'])->toBe('22500.00');     // ✓
expect($result['tax_due'])->toBe(22500.00);        // ✗ FORBIDDEN — float
```

Float comparisons hide rounding bugs that turn into BIR penalties. Use
`Money::php(...)` or hand-formatted decimals (the `php()` helper in `Pest.php`
exists for this).

### 2. One assertion per behaviour, chained when describing the same outcome

```php
it('classifies revenue separately from other income', function () {
    expect($is->totalRevenue)->toBe('1000000.00')
        ->and($is->totalOtherIncome)->toBe('5000.00');
});
```

Multiple `it()` blocks describing the **same setup but different facts** is a
smell — usually means the SUT is doing too much.

### 3. Test names are sentences a CPA would recognise

`it('rejects an issue that would drive quantity below zero')` is correct.
`it('test_apply_negative')` is **not** — names must read like inspector findings,
because that is who will read the failure log during a BIR audit.

### 4. Dates are absolute and inside the Philippine fiscal calendar

Use `new DateTimeImmutable('2026-05-15')` — never `Carbon::now()` in a test, or
the test will start failing on different days. The `APP_TIMEZONE=Asia/Manila`
applies to all tests; this matters for cut-offs around fiscal-year boundaries.

### 5. UUIDs in fixtures use the v7-shaped sentinel form

`018f0000-0000-7000-8000-000000000010` is the canonical "test UUID #10". This
makes test failures grep-friendly and avoids the randomness that
`Str::uuid()` introduces.

---

## Golden-file tests (BIR-critical)

The Bureau of Internal Revenue does not negotiate file formats. A single mis-ordered
field in a SAWT `.DAT` rejects the entire submission — so every BIR output is pinned
to a **byte-exact (or schema-exact for XML)** fixture stored in
[`tests/Fixtures/Bir/`](Fixtures/Bir).

### How they work

1. Compute the expected output **by hand** (or using BIR's own eBIRForms tool)
   for a curated scenario.
2. Commit the result to `tests/Fixtures/Bir/<form>-<period>-<scenario>.<ext>`.
3. The test re-generates the output from the seeded DB / fixture inputs and
   compares **string-equal** (for DAT, plain text) or **canonical-XML-equal**
   (for eBIRForms XML, using XMLCanonicalizer).

```php
it('produces a SAWT DAT matching the BIR-validated fixture', function () {
    $expected = file_get_contents(__DIR__.'/../../../Fixtures/Bir/sawt-2026-Q2.dat');

    $actual = $this->formatter->formatSawt($entries, $header);

    expect($actual)->toBe($expected);     // byte-for-byte
});
```

### Updating a golden file

A golden-file diff is treated as a **regulatory event**, not a code change:

1. Open a PR labelled `bir-format-change`.
2. Cite the BIR issuance (RR / RMC / RMO number) that justifies the change.
3. Re-run the official eBIRForms validator on the new file and attach the
   acknowledgement screenshot to the PR.
4. Tag a CPA reviewer (CODEOWNERS routes `tests/Fixtures/Bir/**` to `@cpa-team`).

**Never** edit a golden file just because "the test broke" — the test is the
specification.

### Fixture inventory (one per filing artefact)

| File                                | Source / Justification                |
| ----------------------------------- | ------------------------------------- |
| `2550M-2026-04-acme.xml`            | RR 13-2018 schema, ACME demo company  |
| `2550Q-2026-Q1-acme.xml`            | RR 13-2018                            |
| `1601EQ-2026-Q1-acme.xml`           | RR 11-2018                            |
| `1601C-2026-04-acme.xml`            | RR 11-2018                            |
| `0619E-2026-04-acme.xml`            | RR 11-2018                            |
| `2307-roberto-garcia-Q2-2026.pdf`   | BIR Form 2307 v.Jan 2018              |
| `2316-roberto-garcia-2025.pdf`      | BIR Form 2316 v.2018                  |
| `1604CF-2025-acme.dat`              | RMC 73-2019 alphalist v7.0            |
| `1604E-2025-acme.dat`               | RMC 73-2019                           |
| `sawt-2026-Q2-acme.dat`             | RR 1-2014                             |
| `qap-2026-Q1-acme.dat`              | RMC 73-2019                           |
| `map-2026-04-acme.dat`              | RMC 73-2019                           |
| `inventory-list-2025-acme.csv`      | RMC 57-2015                           |

(Files appear progressively as each BIR output's action class lands; missing
fixtures mean "form not yet generated end-to-end".)

---

## Custom expectations (defined in `Pest.php`)

| Expectation              | Use                                                |
| ------------------------ | -------------------------------------------------- |
| `->toBeBalancedJournal()` | Asserts `sum(debit) == sum(credit)` on a JE        |

Add new ones for repeated assertions across ≥3 tests; otherwise inline.

---

## Architecture tests (the wall around the modules)

`tests/Architecture/ModuleBoundariesTest.php` enforces:

- A module's `Domain/` never imports Laravel (`Illuminate\…`).
- Module A's `Infrastructure/` never imports module B's `Infrastructure/` — only
  module B's `Contracts/`.
- No `dd()`, `dump()`, `var_dump()` anywhere in `app/`.
- Every controller in `app/Modules/*/Presentation/Http/Controllers/` is either
  a resource controller (exactly the 7 RESTful methods) or a single-action
  `__invoke()` controller. **No other shape passes.**

These run in <1 second and form the first wave of CI checks.

---

## What is **not** tested here

- **`vendor/` packages**: trust your dependencies; pin versions in `composer.json`.
- **Browser rendering**: covered by Playwright in `frontend/e2e/`.
- **BIR portal availability**: covered by `/health/eis-gateway` smoke check, not
  a unit test.
- **PostgreSQL trigger SQL**: covered by `Feature/Modules/Audit/HashChainTest.php`
  with a live test DB — the trigger logic is in `database/migrations/`, not PHP.

---

## Adding a test — quick checklist

- [ ] Filed under the correct layer (`Unit` / `Feature` / `Integration`).
- [ ] Filed under the correct module (`Modules/<Name>/`).
- [ ] Test name reads like a finding, not a method name.
- [ ] Uses string money, absolute dates, sentinel UUIDs.
- [ ] If the test produces a BIR file: pinned to a golden fixture with a cited
      RR/RMC number.
- [ ] Passes locally with `vendor/bin/pest --parallel` before pushing.
