# Accounting Module

The core BIR-compliant general ledger: Chart of Accounts (ltree), journal entries, fiscal periods (with lock enforcement), document series (BIR sequential numbering), FX rates, cost centers.

## Highlights

- **Pure DDD layout** — Domain (entities + value objects + services) is framework-free; Application orchestrates use cases via Actions; Infrastructure adapts to Eloquent + Postgres; Presentation hosts thin controllers.
- **Taylor Otwell controller convention** — `JournalEntryController` and `ChartOfAccountsController` expose only the 7 RESTful methods. Every other verb (`post`, `reverse`, `lock`) is its own single-action invokable controller (`PostJournalEntryController::__invoke`, etc.).
- **BIR CAS compliance baked in at the database level**:
  - `accounting.fiscal_periods.locked_at` + `reject_locked_period_writes()` trigger reject inserts/updates of journal entries in closed periods.
  - `accounting.allocate_doc_no(uuid)` is a row-locking allocator that guarantees gap-free sequential numbering for journal vouchers.
  - `journal_must_balance_at_commit` constraint trigger asserts `SUM(debit) = SUM(credit)` per posted entry, evaluated at COMMIT.
  - `reject_posted_journal_mutation` blocks any field change to a posted entry except the `reversed_by` linkage.
  - DELETE on financial tables is REVOKED in the final lock migration.

## Layout

```
app/Modules/Accounting/
├── routes.php
├── README.md
├── Domain/
│   ├── Entities/{Account, JournalEntry, JournalLine}.php
│   ├── ValueObjects/{Money, AccountId, AccountCode, JournalEntryId}.php
│   ├── Services/DoubleEntryValidator.php
│   ├── Events/JournalPosted.php
│   └── Exceptions/UnbalancedJournalException.php
├── Application/
│   ├── Contracts/{Journal, Account, FiscalPeriod}RepositoryContract.php
│   ├── Actions/{CreateJournalEntry, PostJournalEntry, ReverseJournalEntry, LockFiscalPeriod}.php
│   └── Exceptions/{FiscalPeriodLocked, JournalNotFound, AccountNotPostable}Exception.php
├── Infrastructure/
│   ├── Persistence/
│   │   ├── Eloquent/{Account, JournalEntry, JournalLine, FiscalYear, FiscalPeriod, DocumentSeries, FxRate, CostCenter}Model.php
│   │   ├── Eloquent{Account, Journal, FiscalPeriod}Repository.php
│   └── Providers/AccountingServiceProvider.php
└── Presentation/
    └── Http/
        ├── Controllers/
        │   ├── JournalEntryController.php          ← 7 methods only
        │   ├── ChartOfAccountsController.php       ← 7 methods only
        │   ├── PostJournalEntryController.php      ← __invoke
        │   ├── ReverseJournalEntryController.php   ← __invoke
        │   └── LockFiscalPeriodController.php      ← __invoke
        ├── Requests/{StoreJournalEntry, ReverseJournalEntry, LockFiscalPeriod}Request.php
        └── Resources/{JournalEntry, Account}Resource.php
```

## End-to-end posting flow

```
POST /api/v1/journals                      # creates a draft entry
  → JournalEntryController::store
  → CreateJournalEntry::execute
    ├── validate fiscal period exists + not locked
    ├── allocate doc_no via accounting.allocate_doc_no(uuid)
    ├── persist header + lines (lines may be unbalanced — drafts are tolerant)
    └── audit.write_event('journalentry.drafted')

POST /api/v1/journals/{id}/post            # posts the draft
  → PostJournalEntryController::__invoke   ← single-action, MFA-required
  → PostJournalEntry::execute
    ├── load entry + check not already posted
    ├── DoubleEntryValidator::validate (early app-level check)
    ├── entry->post() (sets posted_at)
    ├── DB-level deferred constraint fires at COMMIT, asserts balance
    ├── audit.write_event('journalentry.posted')  ← hash-chained
    └── dispatch JournalPosted event           (Reporting/Tax may listen)

POST /api/v1/journals/{id}/reverse         # creates the reversal
  → ReverseJournalEntryController::__invoke ← MFA-required
  → ReverseJournalEntry::execute
    ├── flip every line's debit/credit, negate php_amount
    ├── allocate new doc_no
    ├── post the reversal entry
    ├── update original.reversed_by = reversal.id (the only mutation allowed on posted)
    └── audit.write_event('journalentry.reversed')
```

## Database invariants enforced by Postgres

| Invariant | Mechanism |
|---|---|
| Locked period → no writes | trigger `reject_locked_journal_writes` BEFORE INSERT/UPDATE on `journal_entries` |
| Posted entries are immutable | trigger `reject_posted_journal_mutation` BEFORE UPDATE on `journal_entries` |
| Debits = credits per entry | constraint trigger `journal_must_balance_at_commit` (DEFERRABLE INITIALLY DEFERRED) |
| At most one of {debit, credit} > 0 per line | CHECK constraint `one_side_only` |
| Sequential numbering, no gaps | function `accounting.allocate_doc_no(uuid)` with `SELECT … FOR UPDATE` |
| No DELETE on posted entries | REVOKE DELETE in the final lock migration |
```
