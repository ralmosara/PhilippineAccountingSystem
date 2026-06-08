<?php

declare(strict_types=1);

use App\Modules\Tax\Domain\Entities\Form2307Received;
use App\Modules\Tax\Domain\Exceptions\Form2307AlreadyClaimedException;
use App\Modules\Tax\Domain\ValueObjects\Form2307ReceivedId;

/**
 * Domain invariants on the Form 2307 (received) entity.
 *
 * BIR context: every received 2307 is potential tax credit; the entity
 * enforces the status workflow so we never double-claim or mutate a
 * cert that's already on a filed ITR.
 */
function makeCert(array $overrides = []): Form2307Received
{
    $defaults = [
        'id'                  => Form2307ReceivedId::generate(),
        'companyId'           => '018f0000-0000-7000-8000-000000000001',
        'customerId'          => null,
        'payorTin'            => '999-888-777-000',
        'payorRegisteredName' => 'GOV CLIENT INC',
        'payorBranchCode'     => '000',
        'payorAddress'        => 'Quezon City',
        'certificateNo'       => 'C-2026-Q1-0001',
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

it('refuses to construct a cert where taxWithheld exceeds incomePayment', function () {
    expect(fn () => makeCert(['incomePayment' => '100.00', 'taxWithheld' => '101.00']))
        ->toThrow(DomainException::class, 'exceeds incomePayment');
});

it('refuses negative incomePayment', function () {
    expect(fn () => makeCert(['incomePayment' => '-100.00', 'taxWithheld' => '0.00']))
        ->toThrow(DomainException::class, 'cannot be negative');
});

it('refuses an inverted period (from > to)', function () {
    expect(fn () => makeCert([
        'periodFrom' => new DateTimeImmutable('2026-06-30'),
        'periodTo'   => new DateTimeImmutable('2026-01-01'),
    ]))->toThrow(DomainException::class, 'period invalid');
});

it('classifies a recorded cert as claimable', function () {
    $cert = makeCert();
    expect($cert->isClaimable())->toBeTrue();
});

it('flips status to claimed and remembers the BIR form id', function () {
    $cert = makeCert();
    $cert->claim('018f0000-0000-7000-8000-00000000bbbb');

    expect($cert->status)->toBe('claimed')
        ->and($cert->claimedInBirFormId)->toBe('018f0000-0000-7000-8000-00000000bbbb')
        ->and($cert->isClaimable())->toBeFalse();
});

it('idempotent — re-claiming the same form is a no-op', function () {
    $cert = makeCert();
    $cert->claim('018f0000-0000-7000-8000-00000000bbbb');
    $cert->claim('018f0000-0000-7000-8000-00000000bbbb');     // ← same form

    expect($cert->status)->toBe('claimed');
});

it('rejects claiming the same cert against a DIFFERENT BIR form (would be double-credit)', function () {
    $cert = makeCert();
    $cert->claim('018f0000-0000-7000-8000-00000000bbbb');

    expect(fn () => $cert->claim('018f0000-0000-7000-8000-00000000cccc'))
        ->toThrow(Form2307AlreadyClaimedException::class, 'already claimed');
});

it('rejects with reason and marks status terminal', function () {
    $cert = makeCert();
    $cert->reject('Payor TIN mismatch with bank deposit slip');

    expect($cert->status)->toBe('rejected')
        ->and($cert->rejectionReason)->toBe('Payor TIN mismatch with bank deposit slip')
        ->and($cert->isClaimable())->toBeFalse();
});

it('refuses to reject a claimed cert (would orphan the ITR credit)', function () {
    $cert = makeCert();
    $cert->claim('018f0000-0000-7000-8000-00000000bbbb');

    expect(fn () => $cert->reject('typo on amount'))
        ->toThrow(DomainException::class, 'Amend the 1701/1702 first');
});

it('refuses to reject without a reason', function () {
    $cert = makeCert();
    expect(fn () => $cert->reject('   '))
        ->toThrow(DomainException::class, 'Rejection reason is required');
});

it('refuses to claim a rejected cert', function () {
    $cert = makeCert();
    $cert->reject('Duplicate of prior filing');
    expect(fn () => $cert->claim('018f0000-0000-7000-8000-00000000bbbb'))
        ->toThrow(DomainException::class, 'rejected 2307');
});

it('derives quarter from period_to', function () {
    expect(makeCert(['periodTo' => new DateTimeImmutable('2026-01-31')])->quarter())->toBe(1)
        ->and(makeCert(['periodTo' => new DateTimeImmutable('2026-04-30')])->quarter())->toBe(2)
        ->and(makeCert(['periodTo' => new DateTimeImmutable('2026-09-15')])->quarter())->toBe(3)
        ->and(makeCert(['periodTo' => new DateTimeImmutable('2026-12-31')])->quarter())->toBe(4);
});

it('derives fiscal year from period_to', function () {
    expect(makeCert(['periodTo' => new DateTimeImmutable('2026-03-15')])->fiscalYear())->toBe(2026)
        ->and(makeCert(['periodTo' => new DateTimeImmutable('2025-12-31')])->fiscalYear())->toBe(2025);
});
