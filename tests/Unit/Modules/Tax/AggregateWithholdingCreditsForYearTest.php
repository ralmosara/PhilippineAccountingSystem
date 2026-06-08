<?php

declare(strict_types=1);

use App\Modules\Tax\Application\Contracts\Form2307ReceivedRepositoryContract;
use App\Modules\Tax\Application\Queries\AggregateWithholdingCreditsForYear;
use App\Modules\Tax\Domain\Entities\Form2307Received;
use App\Modules\Tax\Domain\ValueObjects\Form2307ReceivedId;

/**
 * Aggregator drives Form 1701 / 1702-RT line 22 (creditable withholding tax).
 * One off-by-one centavo here = BIR overpayment on every filing.
 *
 * Uses an in-memory repository so we don't need Postgres; the contract
 * methods we exercise are the same the Eloquent impl satisfies.
 */
function inMemoryRepo(array $certs): Form2307ReceivedRepositoryContract
{
    return new class($certs) implements Form2307ReceivedRepositoryContract {
        /** @param list<Form2307Received> $certs */
        public function __construct(private array $certs) {}

        public function findById(Form2307ReceivedId $id): ?Form2307Received
        {
            foreach ($this->certs as $c) {
                if ($c->id->value === $id->value) return $c;
            }
            return null;
        }
        public function save(Form2307Received $cert): void {}
        public function delete(Form2307ReceivedId $id): void {}
        public function findRecordedInPeriod(string $companyId, DateTimeImmutable $from, DateTimeImmutable $to): array
        {
            return array_values(array_filter($this->certs, fn (Form2307Received $c) =>
                $c->companyId === $companyId
                && $c->status === 'recorded'
                && $c->periodTo >= $from
                && $c->periodTo <= $to
            ));
        }
        public function markClaimed(array $ids, string $birFormId): void {}
        public function existsDuplicate(string $companyId, string $payorTin, DateTimeImmutable $from, DateTimeImmutable $to, string $atc, ?string $cert): bool
        {
            return false;
        }
    };
}

function buildCert(array $overrides = []): Form2307Received
{
    $defaults = [
        'id'                  => Form2307ReceivedId::generate(),
        'companyId'           => '018f0000-0000-7000-8000-000000000001',
        'customerId'          => null,
        'payorTin'            => '999-888-777-000',
        'payorRegisteredName' => 'GOV CLIENT INC',
        'payorBranchCode'     => '000',
        'payorAddress'        => null,
        'certificateNo'       => null,
        'atcCode'             => 'WI010',
        'periodFrom'          => new DateTimeImmutable('2026-01-01'),
        'periodTo'            => new DateTimeImmutable('2026-03-31'),
        'incomePayment'       => '100000.00',
        'taxWithheld'         => '5000.00',
        'sourcePdfPath'       => null,
        'entryMethod'         => 'manual',
        'journalEntryId'      => null,
        'recordedBy'          => '018f0000-0000-7000-8000-00000000aaaa',
        'status'              => 'recorded',
    ];
    return new Form2307Received(...array_merge($defaults, $overrides));
}

it('returns zero credit when no 2307s on file', function () {
    $agg = new AggregateWithholdingCreditsForYear(inMemoryRepo([]));
    $sum = $agg->execute('018f0000-0000-7000-8000-000000000001', 2026);

    expect($sum->totalTaxWithheld)->toBe('0.00')
        ->and($sum->totalIncomePayment)->toBe('0.00')
        ->and($sum->claimableCertIds)->toBe([])
        ->and($sum->byPayor)->toBe([])
        ->and($sum->byAtc)->toBe([]);
});

it('sums tax_withheld across all recorded certs in the fiscal year', function () {
    $certs = [
        buildCert(['taxWithheld' => '5000.00',  'incomePayment' => '100000.00']),
        buildCert(['taxWithheld' => '12500.00', 'incomePayment' => '250000.00']),
        buildCert(['taxWithheld' => '2500.00',  'incomePayment' => '50000.00']),
    ];

    $sum = (new AggregateWithholdingCreditsForYear(inMemoryRepo($certs)))
        ->execute('018f0000-0000-7000-8000-000000000001', 2026);

    expect($sum->totalTaxWithheld)->toBe('20000.00')
        ->and($sum->totalIncomePayment)->toBe('400000.00')
        ->and($sum->claimableCertIds)->toHaveCount(3);
});

it('groups by (payor TIN, ATC) so the alphalist emits one row per pair', function () {
    $certs = [
        // Same payor, same ATC, three quarters → one rollup row
        buildCert(['payorTin' => '111-111-111-000', 'atcCode' => 'WI010', 'taxWithheld' => '1000.00', 'incomePayment' => '20000.00', 'periodTo' => new DateTimeImmutable('2026-03-31')]),
        buildCert(['payorTin' => '111-111-111-000', 'atcCode' => 'WI010', 'taxWithheld' => '1500.00', 'incomePayment' => '30000.00', 'periodTo' => new DateTimeImmutable('2026-06-30')]),
        buildCert(['payorTin' => '111-111-111-000', 'atcCode' => 'WI010', 'taxWithheld' => '2000.00', 'incomePayment' => '40000.00', 'periodTo' => new DateTimeImmutable('2026-09-30')]),

        // Same payor, different ATC → different rollup row
        buildCert(['payorTin' => '111-111-111-000', 'atcCode' => 'WC158', 'taxWithheld' => '500.00',  'incomePayment' => '25000.00']),

        // Different payor entirely
        buildCert(['payorTin' => '222-222-222-000', 'atcCode' => 'WI010', 'taxWithheld' => '750.00',  'incomePayment' => '15000.00']),
    ];

    $sum = (new AggregateWithholdingCreditsForYear(inMemoryRepo($certs)))
        ->execute('018f0000-0000-7000-8000-000000000001', 2026);

    expect($sum->byPayor)->toHaveCount(3);

    // Find the (111…, WI010) rollup
    $main = current(array_filter($sum->byPayor, fn ($r) => $r['payor_tin'] === '111-111-111-000' && $r['atc_code'] === 'WI010'));
    expect($main['tax_withheld'])->toBe('4500.00')
        ->and($main['income_payment'])->toBe('90000.00')
        ->and($main['cert_count'])->toBe(3);
});

it('rolls up totals by ATC for diagnostic display', function () {
    $certs = [
        buildCert(['atcCode' => 'WI010', 'taxWithheld' => '1000.00', 'incomePayment' => '20000.00']),
        buildCert(['atcCode' => 'WI010', 'taxWithheld' => '2000.00', 'incomePayment' => '40000.00']),
        buildCert(['atcCode' => 'WC158', 'taxWithheld' => '500.00',  'incomePayment' => '25000.00']),
    ];

    $sum = (new AggregateWithholdingCreditsForYear(inMemoryRepo($certs)))
        ->execute('018f0000-0000-7000-8000-000000000001', 2026);

    expect($sum->byAtc)->toMatchArray([
        'WI010' => '3000.00',
        'WC158' => '500.00',
    ]);
});

it('excludes certs from other fiscal years', function () {
    $certs = [
        buildCert(['periodTo' => new DateTimeImmutable('2025-12-31'), 'taxWithheld' => '1000.00', 'incomePayment' => '20000.00']),
        buildCert(['periodTo' => new DateTimeImmutable('2026-03-31'), 'taxWithheld' => '5000.00', 'incomePayment' => '100000.00']),
        buildCert(['periodTo' => new DateTimeImmutable('2027-01-31'), 'taxWithheld' => '7000.00', 'incomePayment' => '140000.00']),
    ];

    $sum = (new AggregateWithholdingCreditsForYear(inMemoryRepo($certs)))
        ->execute('018f0000-0000-7000-8000-000000000001', 2026);

    // Only the 2026-03-31 cert is in fiscal year 2026
    expect($sum->totalTaxWithheld)->toBe('5000.00')
        ->and($sum->claimableCertIds)->toHaveCount(1);
});

it('excludes already-claimed and rejected certs even if the repo returns them', function () {
    // findRecordedInPeriod SHOULD filter by status='recorded' (the Eloquent
    // impl does), but the aggregator must defensively double-check so a buggy
    // repo can't cause a double-claim.
    $certs = [
        buildCert(['taxWithheld' => '1000.00', 'incomePayment' => '20000.00', 'status' => 'recorded']),
        buildCert(['taxWithheld' => '2000.00', 'incomePayment' => '40000.00', 'status' => 'claimed']),
        buildCert(['taxWithheld' => '3000.00', 'incomePayment' => '60000.00', 'status' => 'rejected']),
    ];

    $brokenRepo = new class($certs) implements Form2307ReceivedRepositoryContract {
        public function __construct(private array $certs) {}
        public function findById(Form2307ReceivedId $id): ?Form2307Received { return null; }
        public function save(Form2307Received $cert): void {}
        public function delete(Form2307ReceivedId $id): void {}
        public function findRecordedInPeriod(string $companyId, DateTimeImmutable $from, DateTimeImmutable $to): array
        {
            return $this->certs;       // ← intentionally returns ALL statuses
        }
        public function markClaimed(array $ids, string $birFormId): void {}
        public function existsDuplicate(string $companyId, string $payorTin, DateTimeImmutable $from, DateTimeImmutable $to, string $atc, ?string $cert): bool
        {
            return false;
        }
    };

    $sum = (new AggregateWithholdingCreditsForYear($brokenRepo))
        ->execute('018f0000-0000-7000-8000-000000000001', 2026);

    expect($sum->totalTaxWithheld)->toBe('1000.00')           // only the 'recorded' one
        ->and($sum->claimableCertIds)->toHaveCount(1);
});
