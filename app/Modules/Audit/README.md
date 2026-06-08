# Audit Module

Bounded context: **hash-chained immutable event log + backup logs + security events.**

This module is BIR CAS-critical (RR 9-2009 §6.2). Every consequential write to a financial table emits an audit event. The chain is verified daily and tamper-proof at the database level.

## Structure

```
app/Modules/Audit/
├── Application/
│   ├── Actions/
│   │   └── VerifyAuditChain.php
│   └── Contracts/
│       └── AuditWriterContract.php       # public surface for other modules
├── Infrastructure/
│   ├── Persistence/
│   │   └── PostgresAuditWriter.php       # implements AuditWriterContract
│   └── Providers/
│       └── AuditServiceProvider.php
└── Presentation/
    └── Console/
        └── VerifyAuditChainCommand.php   # php artisan audit:verify-chain
```

## Usage

```php
final class SomeModuleAction
{
    public function __construct(
        private AuditWriterContract $audit,
    ) {}

    public function execute(...): void
    {
        DB::transaction(function () {
            // ... domain mutation ...

            $this->audit->writeEvent(
                actorId: $user->id,
                companyId: $companyId,
                eventType: 'journalentry.posted',
                aggregate: 'JournalEntry',
                aggregateId: $journalEntry->id,
                payload: ['lines' => $journalEntry->lines->toArray()],
            );
        });
    }
}
```

If the surrounding transaction rolls back, the audit row rolls back with it. No orphaned events.

## Verification

```bash
php artisan audit:verify-chain
# Verified 12,453 events; 0 tampered.

php artisan audit:verify-chain --company=018f1234-...-...
```

Scheduler runs this daily at 02:00 (see `routes/console.php`).
