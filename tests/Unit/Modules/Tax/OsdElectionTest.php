<?php

declare(strict_types=1);

use App\Modules\Tax\Domain\Entities\OsdElection;
use App\Modules\Tax\Domain\Exceptions\OsdElectionMismatchException;
use App\Modules\Tax\Domain\ValueObjects\OsdElectionId;

/**
 * Domain invariants on tax.osd_elections rows.
 * The election is the year's frozen regulatory commitment; the entity's
 * constructor + assertMatches guard against every illegal mutation we
 * can express in PHP rather than waiting for a Postgres unique-key
 * violation at write time.
 */
function makeElection(array $overrides = []): OsdElection
{
    $defaults = [
        'id'                  => OsdElectionId::generate(),
        'companyId'           => '018f0000-0000-7000-8000-000000000001',
        'fiscalYear'          => 2026,
        'taxpayerType'        => 'individual',
        'regime'              => 'osd',
        'declaredInFormType'  => '1701Q',
        'declaredInQuarter'   => 1,
        'declaredInBirFormId' => '018f0000-0000-7000-8000-000000000bbb',
        'lockedAt'            => new DateTimeImmutable('2026-05-15'),
        'lockedBy'            => '018f0000-0000-7000-8000-000000000aaa',
        'replacesId'          => null,
    ];
    return new OsdElection(...array_merge($defaults, $overrides));
}

it('accepts the three valid regimes', function () {
    expect(makeElection(['regime' => 'itemized'])->regime)->toBe('itemized')
        ->and(makeElection(['regime' => 'osd'])->regime)->toBe('osd')
        ->and(makeElection(['regime' => 'flat_8pct'])->regime)->toBe('flat_8pct');
});

it('rejects an invalid regime label', function () {
    expect(fn () => makeElection(['regime' => 'banana']))
        ->toThrow(DomainException::class, "Invalid regime 'banana'");
});

it('rejects an invalid taxpayer_type label', function () {
    expect(fn () => makeElection(['taxpayerType' => 'partnership']))
        ->toThrow(DomainException::class, "Invalid taxpayer_type 'partnership'");
});

it('restricts the 8% flat regime to individuals (RA 10963)', function () {
    expect(fn () => makeElection(['taxpayerType' => 'corporate', 'regime' => 'flat_8pct']))
        ->toThrow(DomainException::class, 'restricted to individual taxpayers');
});

it('rejects declared_in_quarter outside [1..4] when supplied', function () {
    expect(fn () => makeElection(['declaredInQuarter' => 0]))
        ->toThrow(DomainException::class, 'Invalid declared_in_quarter 0')
        ->and(fn () => makeElection(['declaredInQuarter' => 5]))
        ->toThrow(DomainException::class);
});

it('accepts null declared_in_quarter (annual-declared election)', function () {
    $e = makeElection(['declaredInQuarter' => null, 'declaredInFormType' => '1701']);
    expect($e->declaredInQuarter)->toBeNull();
});

it('assertMatches passes when the supplied regime matches the locked one', function () {
    $e = makeElection(['regime' => 'osd']);
    expect(fn () => $e->assertMatches('osd'))->not->toThrow(Exception::class);
});

it('assertMatches throws OsdElectionMismatchException with a regulatory citation', function () {
    $e = makeElection(['regime' => 'itemized']);

    expect(fn () => $e->assertMatches('osd'))
        ->toThrow(OsdElectionMismatchException::class)
        ->and(fn () => $e->assertMatches('osd'))
        ->toThrow(OsdElectionMismatchException::class, "regime 'osd' contradicts");
});

it('supersede marks the election inactive with reason + timestamp', function () {
    $e = makeElection();
    $e->supersede('BIR approved an amendment to switch from OSD to itemized');

    expect($e->isActive())->toBeFalse()
        ->and($e->supersededAt)->not->toBeNull()
        ->and($e->supersedeReason)->toContain('BIR approved');
});

it('refuses to supersede twice (status terminal)', function () {
    $e = makeElection();
    $e->supersede('first amendment');

    expect(fn () => $e->supersede('attempted second amendment'))
        ->toThrow(DomainException::class, 'already superseded');
});

it('refuses to supersede with a blank reason', function () {
    $e = makeElection();
    expect(fn () => $e->supersede('   '))
        ->toThrow(DomainException::class, 'Supersede reason is required');
});

it('isActive reflects the supersededAt sentinel only', function () {
    $a = makeElection();
    expect($a->isActive())->toBeTrue();

    $a->supersede('any reason');
    expect($a->isActive())->toBeFalse();
});
