<?php

declare(strict_types=1);

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Tax\Application\Actions\RecordOrConfirmOsdElection;
use App\Modules\Tax\Application\Contracts\OsdElectionRepositoryContract;
use App\Modules\Tax\Domain\Entities\OsdElection;
use App\Modules\Tax\Domain\Exceptions\OsdElectionMismatchException;
use App\Modules\Tax\Domain\ValueObjects\OsdElectionId;

/**
 * Tests the lock-vs-record-vs-mismatch decision tree of the OSD election
 * action. Uses in-memory implementations of both the repository and audit
 * writer so we exercise the real branching logic without Postgres.
 */
function inMemoryElectionRepo(): OsdElectionRepositoryContract
{
    return new class implements OsdElectionRepositoryContract {
        /** @var array<string, OsdElection> */
        private array $store = [];

        public function findById(OsdElectionId $id): ?OsdElection
        {
            return $this->store[$id->value] ?? null;
        }

        public function findActive(string $companyId, int $fiscalYear, string $taxpayerType): ?OsdElection
        {
            foreach ($this->store as $e) {
                if ($e->companyId === $companyId
                    && $e->fiscalYear === $fiscalYear
                    && $e->taxpayerType === $taxpayerType
                    && $e->isActive()) {
                    return $e;
                }
            }
            return null;
        }

        public function save(OsdElection $e): void
        {
            $this->store[$e->id->value] = $e;
        }
    };
}

function noopAuditWriter(): AuditWriterContract
{
    return new class implements AuditWriterContract {
        public int $writeCount = 0;

        public function writeEvent(?string $actorId, string $companyId, string $eventType, string $aggregate, string $aggregateId, array $payload, ?string $ipAddress = null, ?string $userAgent = null, ?string $requestId = null): int
        {
            $this->writeCount++;
            return $this->writeCount;
        }

        public function writeSecurityEvent(string $eventType, ?string $userId, ?string $ipAddress = null, array $details = []): int
        {
            return 0;
        }
    };
}

beforeEach(function () {
    $this->repo  = inMemoryElectionRepo();
    $this->audit = noopAuditWriter();
    $this->action = new RecordOrConfirmOsdElection($this->repo, $this->audit);
    $this->actor = '018f0000-0000-7000-8000-000000000aaa';
    $this->company = '018f0000-0000-7000-8000-000000000001';
});

it('records a new election when none exists for the year', function () {
    $election = $this->action->execute(
        companyId:           $this->company,
        fiscalYear:          2026,
        taxpayerType:        'individual',
        regime:              'osd',
        declaredInFormType:  '1701Q',
        declaredInQuarter:   1,
        declaredInBirFormId: null,
        actorId:             $this->actor,
    );

    expect($election->regime)->toBe('osd')
        ->and($election->fiscalYear)->toBe(2026)
        ->and($election->declaredInQuarter)->toBe(1)
        ->and($election->isActive())->toBeTrue();
});

it('writes a single audit event on the first-record call', function () {
    $this->action->execute(
        companyId:    $this->company, fiscalYear: 2026, taxpayerType: 'individual',
        regime:       'osd', declaredInFormType: '1701Q', declaredInQuarter: 1,
        declaredInBirFormId: null, actorId: $this->actor,
    );

    expect($this->audit->writeCount)->toBe(1);
});

it('returns the existing election unchanged on a second call with matching regime (idempotent)', function () {
    $first = $this->action->execute(
        companyId: $this->company, fiscalYear: 2026, taxpayerType: 'individual',
        regime: 'osd', declaredInFormType: '1701Q', declaredInQuarter: 1,
        declaredInBirFormId: null, actorId: $this->actor,
    );

    // Second call: Q2 confirms regime; should return Q1's election, no new audit row
    $second = $this->action->execute(
        companyId: $this->company, fiscalYear: 2026, taxpayerType: 'individual',
        regime: 'osd', declaredInFormType: '1701Q', declaredInQuarter: 2,
        declaredInBirFormId: null, actorId: $this->actor,
    );

    expect($second->id->value)->toBe($first->id->value)
        ->and($second->declaredInQuarter)->toBe(1)         // still Q1's record, not overwritten
        ->and($this->audit->writeCount)->toBe(1);          // no second audit write
});

it('throws OsdElectionMismatchException when subsequent call uses a different regime', function () {
    // Q1 locks in 'itemized'
    $this->action->execute(
        companyId: $this->company, fiscalYear: 2026, taxpayerType: 'individual',
        regime: 'itemized', declaredInFormType: '1701Q', declaredInQuarter: 1,
        declaredInBirFormId: null, actorId: $this->actor,
    );

    // Q2 attempts to switch to OSD → must throw
    expect(fn () => $this->action->execute(
        companyId: $this->company, fiscalYear: 2026, taxpayerType: 'individual',
        regime: 'osd', declaredInFormType: '1701Q', declaredInQuarter: 2,
        declaredInBirFormId: null, actorId: $this->actor,
    ))->toThrow(OsdElectionMismatchException::class, "regime 'osd' contradicts");
});

it('isolates elections by taxpayer_type (sole-prop + own corp do not collide)', function () {
    // Same company holds BOTH an individual sole-prop AND a corporate entity.
    // Each gets its own (year, taxpayer_type) election row.
    $individual = $this->action->execute(
        companyId: $this->company, fiscalYear: 2026, taxpayerType: 'individual',
        regime: 'flat_8pct', declaredInFormType: '1701Q', declaredInQuarter: 1,
        declaredInBirFormId: null, actorId: $this->actor,
    );

    $corporate = $this->action->execute(
        companyId: $this->company, fiscalYear: 2026, taxpayerType: 'corporate',
        regime: 'osd', declaredInFormType: '1702Q', declaredInQuarter: 1,
        declaredInBirFormId: null, actorId: $this->actor,
    );

    expect($individual->regime)->toBe('flat_8pct')
        ->and($corporate->regime)->toBe('osd')
        ->and($individual->id->value)->not->toBe($corporate->id->value);
});

it('isolates elections by fiscal year (a 2026 lock does not bind 2027)', function () {
    $y26 = $this->action->execute(
        companyId: $this->company, fiscalYear: 2026, taxpayerType: 'individual',
        regime: 'osd', declaredInFormType: '1701Q', declaredInQuarter: 1,
        declaredInBirFormId: null, actorId: $this->actor,
    );
    $y27 = $this->action->execute(
        companyId: $this->company, fiscalYear: 2027, taxpayerType: 'individual',
        regime: 'itemized', declaredInFormType: '1701Q', declaredInQuarter: 1,
        declaredInBirFormId: null, actorId: $this->actor,
    );

    expect($y26->regime)->toBe('osd')
        ->and($y27->regime)->toBe('itemized')
        ->and($y26->id->value)->not->toBe($y27->id->value);
});

it('annual filing inherits the regime locked by an earlier quarterly', function () {
    // Q1 1701Q locks itemized
    $this->action->execute(
        companyId: $this->company, fiscalYear: 2026, taxpayerType: 'individual',
        regime: 'itemized', declaredInFormType: '1701Q', declaredInQuarter: 1,
        declaredInBirFormId: null, actorId: $this->actor,
    );

    // Annual 1701 tries to switch to OSD → must throw, can't override mid-year
    expect(fn () => $this->action->execute(
        companyId: $this->company, fiscalYear: 2026, taxpayerType: 'individual',
        regime: 'osd', declaredInFormType: '1701', declaredInQuarter: null,
        declaredInBirFormId: null, actorId: $this->actor,
    ))->toThrow(OsdElectionMismatchException::class);
});

it('annual filing can establish the lock when no Q-returns preceded it', function () {
    // No Q-returns this year — annual is the first declaration.
    $annual = $this->action->execute(
        companyId: $this->company, fiscalYear: 2026, taxpayerType: 'corporate',
        regime: 'osd', declaredInFormType: '1702RT', declaredInQuarter: null,
        declaredInBirFormId: null, actorId: $this->actor,
    );

    expect($annual->declaredInFormType)->toBe('1702RT')
        ->and($annual->declaredInQuarter)->toBeNull()
        ->and($annual->regime)->toBe('osd');
});
