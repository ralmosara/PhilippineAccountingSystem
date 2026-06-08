<?php

declare(strict_types=1);

use App\Modules\Sales\Application\Queries\GetCustomerArAging;
use Illuminate\Database\ConnectionInterface;

/**
 * Bucket-boundary tests for AR aging.
 *
 * The Query takes the Postgres ConnectionInterface and runs one CTE-style
 * SELECT. We stub the connection with an in-memory double that returns
 * pre-computed rows (Postgres did the days-overdue math), so we exercise
 * ONLY the PHP-side bucketing + BCMath summation — exactly the code that
 * lives in `execute()` after the SQL returns.
 *
 * Why this matters: BIR auditors squint at the bucket boundaries first.
 * A row at exactly 30 days overdue lands in `1–30 days` (inclusive), not
 * `31–60`. These tests pin every edge.
 */

beforeEach(function () {
    $this->customerId = '018f0000-0000-7000-8000-000000000010';
    $this->asOf       = new DateTimeImmutable('2026-05-15');

    // Helper: build a connection double that returns the given rows from
    // any call to `select()`. Each "row" is a stdClass with the columns
    // the SQL CTE projects (id, invoice_total, paid_total, balance, due_date, days_overdue).
    $this->stubConnection = function (array $rows): ConnectionInterface {
        return new class($rows) implements ConnectionInterface {
            /** @param array<int, object> $rows */
            public function __construct(private array $rows) {}
            public function select($query, $bindings = [], $useReadPdo = true): array
            {
                return $this->rows;
            }
            // Other interface methods unused by the Query — return safe defaults.
            public function table($table, $as = null) { return null; }
            public function raw($value)                  { return $value; }
            public function selectOne($query, $bindings = [], $useReadPdo = true) { return null; }
            public function selectFromWriteConnection($query, $bindings = []) { return []; }
            public function cursor($query, $bindings = [], $useReadPdo = true) {
                return new ArrayIterator([]);
            }
            public function insert($query, $bindings = []): bool { return true; }
            public function update($query, $bindings = []): int  { return 0; }
            public function delete($query, $bindings = []): int  { return 0; }
            public function statement($query, $bindings = []): bool { return true; }
            public function affectingStatement($query, $bindings = []): int { return 0; }
            public function unprepared($query): bool { return true; }
            public function prepareBindings(array $bindings): array { return $bindings; }
            public function transaction(\Closure $callback, $attempts = 1) { return $callback($this); }
            public function beginTransaction(): void {}
            public function commit(): void {}
            public function rollBack($toLevel = null): void {}
            public function transactionLevel(): int { return 0; }
            public function pretend(\Closure $callback): array { return []; }
            public function getDatabaseName() { return 'test'; }
        };
    };

    // Helper to build a row object with the columns the SQL projects.
    $this->row = function (string $balance, int $daysOverdue, ?string $invoiceId = null): object {
        return (object) [
            'id'             => $invoiceId ?? '018f0000-0000-7000-8000-000000000aaa',
            'invoice_total'  => $balance,
            'paid_total'     => '0.00',
            'balance'        => $balance,
            'due_date'       => '2026-04-15',
            'days_overdue'   => $daysOverdue,
        ];
    };
});

it('puts a not-yet-due invoice (days_overdue=0) in the Current bucket', function () {
    $conn = ($this->stubConnection)([($this->row)('1000.00', 0)]);
    $aging = (new GetCustomerArAging($conn))->execute($this->customerId, $this->asOf);

    expect($aging->current)->toBe('1000.00')
        ->and($aging->bucket_1_30)->toBe('0.00')
        ->and($aging->total)->toBe('1000.00');
});

it('puts a -5 days_overdue invoice (5 days BEFORE due) in Current', function () {
    // Negative days_overdue means due date is in the future
    $conn = ($this->stubConnection)([($this->row)('500.00', -5)]);
    $aging = (new GetCustomerArAging($conn))->execute($this->customerId, $this->asOf);

    expect($aging->current)->toBe('500.00');
});

it('lands a 1-day-overdue invoice in 1-30 bucket (NOT Current)', function () {
    $conn = ($this->stubConnection)([($this->row)('200.00', 1)]);
    $aging = (new GetCustomerArAging($conn))->execute($this->customerId, $this->asOf);

    expect($aging->current)->toBe('0.00')
        ->and($aging->bucket_1_30)->toBe('200.00');
});

it('lands a 30-day-overdue invoice in 1-30 (inclusive upper bound)', function () {
    $conn = ($this->stubConnection)([($this->row)('300.00', 30)]);
    $aging = (new GetCustomerArAging($conn))->execute($this->customerId, $this->asOf);

    expect($aging->bucket_1_30)->toBe('300.00')
        ->and($aging->bucket_31_60)->toBe('0.00');
});

it('lands a 31-day-overdue invoice in 31-60 (one-past boundary)', function () {
    $conn = ($this->stubConnection)([($this->row)('400.00', 31)]);
    $aging = (new GetCustomerArAging($conn))->execute($this->customerId, $this->asOf);

    expect($aging->bucket_31_60)->toBe('400.00')
        ->and($aging->bucket_1_30)->toBe('0.00');
});

it('lands a 60-day-overdue invoice in 31-60 (inclusive upper bound)', function () {
    $conn = ($this->stubConnection)([($this->row)('500.00', 60)]);
    $aging = (new GetCustomerArAging($conn))->execute($this->customerId, $this->asOf);

    expect($aging->bucket_31_60)->toBe('500.00')
        ->and($aging->bucket_61_90)->toBe('0.00');
});

it('lands a 61-day-overdue invoice in 61-90', function () {
    $conn = ($this->stubConnection)([($this->row)('600.00', 61)]);
    $aging = (new GetCustomerArAging($conn))->execute($this->customerId, $this->asOf);

    expect($aging->bucket_61_90)->toBe('600.00');
});

it('lands a 90-day-overdue invoice in 61-90 (inclusive upper bound)', function () {
    $conn = ($this->stubConnection)([($this->row)('700.00', 90)]);
    $aging = (new GetCustomerArAging($conn))->execute($this->customerId, $this->asOf);

    expect($aging->bucket_61_90)->toBe('700.00')
        ->and($aging->bucket_91_plus)->toBe('0.00');
});

it('lands a 91-day-overdue invoice in 91+ (the "alarm" bucket)', function () {
    $conn = ($this->stubConnection)([($this->row)('800.00', 91)]);
    $aging = (new GetCustomerArAging($conn))->execute($this->customerId, $this->asOf);

    expect($aging->bucket_91_plus)->toBe('800.00');
});

it('lands a 365-day-overdue invoice in 91+ (no upper bound on the worst bucket)', function () {
    $conn = ($this->stubConnection)([($this->row)('1500.00', 365)]);
    $aging = (new GetCustomerArAging($conn))->execute($this->customerId, $this->asOf);

    expect($aging->bucket_91_plus)->toBe('1500.00')
        ->and($aging->total)->toBe('1500.00');
});

/* ── Cross-bucket summation ───────────────────────────────────────────── */

it('sums multiple invoices into their respective buckets and the grand total', function () {
    $conn = ($this->stubConnection)([
        ($this->row)('100.00',   0, '018f0000-0000-7000-8000-0000000000a1'),  // Current
        ($this->row)('200.00',  15, '018f0000-0000-7000-8000-0000000000a2'),  // 1-30
        ($this->row)('300.00',  45, '018f0000-0000-7000-8000-0000000000a3'),  // 31-60
        ($this->row)('400.00',  75, '018f0000-0000-7000-8000-0000000000a4'),  // 61-90
        ($this->row)('500.00', 120, '018f0000-0000-7000-8000-0000000000a5'),  // 91+
    ]);

    $aging = (new GetCustomerArAging($conn))->execute($this->customerId, $this->asOf);

    expect($aging->current)->toBe('100.00')
        ->and($aging->bucket_1_30)->toBe('200.00')
        ->and($aging->bucket_31_60)->toBe('300.00')
        ->and($aging->bucket_61_90)->toBe('400.00')
        ->and($aging->bucket_91_plus)->toBe('500.00')
        ->and($aging->total)->toBe('1500.00')
        ->and($aging->unpaidInvoiceCount)->toBe(5);
});

it('accumulates multiple invoices in the SAME bucket (no overwrite)', function () {
    $conn = ($this->stubConnection)([
        ($this->row)('100.00', 15, '018f0000-0000-7000-8000-0000000000b1'),
        ($this->row)('150.00', 20, '018f0000-0000-7000-8000-0000000000b2'),
        ($this->row)('250.00', 28, '018f0000-0000-7000-8000-0000000000b3'),
    ]);

    $aging = (new GetCustomerArAging($conn))->execute($this->customerId, $this->asOf);

    expect($aging->bucket_1_30)->toBe('500.00')
        ->and($aging->total)->toBe('500.00')
        ->and($aging->unpaidInvoiceCount)->toBe(3);
});

it('uses BCMath precision (no Number drift across 200 invoices)', function () {
    // 200 invoices at ₱0.01 = ₱2.00 exactly; JS Number arithmetic would drift.
    $rows = [];
    for ($i = 0; $i < 200; $i++) {
        $rows[] = ($this->row)('0.01', 15, sprintf('018f0000-0000-7000-8000-%012d', $i));
    }
    $conn = ($this->stubConnection)($rows);

    $aging = (new GetCustomerArAging($conn))->execute($this->customerId, $this->asOf);

    expect($aging->bucket_1_30)->toBe('2.00')
        ->and($aging->total)->toBe('2.00');
});

/* ── Empty + count semantics ──────────────────────────────────────────── */

it('returns zero buckets + zero count when the customer has no unpaid invoices', function () {
    $conn = ($this->stubConnection)([]);
    $aging = (new GetCustomerArAging($conn))->execute($this->customerId, $this->asOf);

    expect($aging->current)->toBe('0.00')
        ->and($aging->bucket_1_30)->toBe('0.00')
        ->and($aging->bucket_31_60)->toBe('0.00')
        ->and($aging->bucket_61_90)->toBe('0.00')
        ->and($aging->bucket_91_plus)->toBe('0.00')
        ->and($aging->total)->toBe('0.00')
        ->and($aging->unpaidInvoiceCount)->toBe(0);
});

it('round-trips customerId + asOf into the DTO', function () {
    $conn = ($this->stubConnection)([]);
    $aging = (new GetCustomerArAging($conn))->execute(
        '018f0000-0000-7000-8000-0000000000ff',
        new DateTimeImmutable('2026-08-15'),
    );

    expect($aging->customerId)->toBe('018f0000-0000-7000-8000-0000000000ff')
        ->and($aging->asOf->format('Y-m-d'))->toBe('2026-08-15');
});
