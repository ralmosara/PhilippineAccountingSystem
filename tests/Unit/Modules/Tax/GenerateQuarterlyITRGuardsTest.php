<?php

declare(strict_types=1);

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Tax\Application\Actions\GenerateForm1701Q;
use App\Modules\Tax\Application\Actions\GenerateForm1702Q;
use App\Modules\Tax\Application\Contracts\AnnualIncomeTaxAggregatorContract;
use App\Modules\Tax\Application\Contracts\BirFormRepositoryContract;
use App\Modules\Tax\Application\Contracts\Form2307ReceivedRepositoryContract;
use App\Modules\Tax\Application\Contracts\PdfRendererContract;
use App\Modules\Tax\Application\Queries\AggregateWithholdingCreditsForYear;
use App\Modules\Tax\Domain\Services\CorporateIncomeTaxCalculator;
use App\Modules\Tax\Domain\Services\IndividualIncomeTaxCalculator;
use App\Modules\Tax\Domain\Services\OsdCalculator;

/**
 * Pre-flight guards on the quarterly ITR actions — the checks that fire
 * BEFORE we touch the DB or aggregator. These are testable in isolation
 * because the action's first lines throw before any infrastructure runs.
 *
 * Action-internal logic (aggregation, cumulative math, line-by-line BIR
 * mapping) needs a live Postgres + container and lives in Feature tests.
 */
function stubRepos(): array
{
    $forms = new class implements BirFormRepositoryContract {
        public function findById(\App\Modules\Tax\Domain\ValueObjects\BirFormId $id): ?\App\Modules\Tax\Domain\Entities\BirForm { return null; }
        public function findByPeriod(string $companyId, string $formType, \App\Modules\Tax\Domain\ValueObjects\FormPeriod $period): ?\App\Modules\Tax\Domain\Entities\BirForm { return null; }
        public function save(\App\Modules\Tax\Domain\Entities\BirForm $form): void {}
    };
    $agg = new class implements AnnualIncomeTaxAggregatorContract {
        public function fiscalYearTotals(string $companyId, \App\Modules\Tax\Domain\ValueObjects\FormPeriod $period): array
        { return ['gross_revenue'=>'0.00','sales_returns'=>'0.00','cost_of_sales'=>'0.00','operating_expenses'=>'0.00','other_income'=>'0.00','other_expenses'=>'0.00','accrued_income_tax'=>'0.00','net_income_before_tax'=>'0.00']; }
        public function totalAssetsAsOf(string $companyId, \App\Modules\Tax\Domain\ValueObjects\FormPeriod $period): string { return '0.00'; }
        public function priorQuarterTaxPayments(string $companyId, string $formType, int $year, int $upToQuarter): string { return '0.00'; }
        public function creditableWithholdingTaxReceived(string $companyId, \App\Modules\Tax\Domain\ValueObjects\FormPeriod $period): string { return '0.00'; }
    };
    $form2307 = new class implements Form2307ReceivedRepositoryContract {
        public function findById(\App\Modules\Tax\Domain\ValueObjects\Form2307ReceivedId $id): ?\App\Modules\Tax\Domain\Entities\Form2307Received { return null; }
        public function save(\App\Modules\Tax\Domain\Entities\Form2307Received $cert): void {}
        public function delete(\App\Modules\Tax\Domain\ValueObjects\Form2307ReceivedId $id): void {}
        public function findRecordedInPeriod(string $companyId, DateTimeImmutable $from, DateTimeImmutable $to): array { return []; }
        public function markClaimed(array $ids, string $birFormId): void {}
        public function existsDuplicate(string $companyId, string $payorTin, DateTimeImmutable $from, DateTimeImmutable $to, string $atc, ?string $cert): bool { return false; }
    };
    $pdf = new class implements PdfRendererContract {
        public function render(\App\Modules\Tax\Domain\Entities\BirForm $form): string { return '/dev/null'; }
    };
    $audit = new class implements AuditWriterContract {
        public function writeEvent(?string $actorId, string $companyId, string $eventType, string $aggregate, string $aggregateId, array $payload, ?string $ipAddress = null, ?string $userAgent = null, ?string $requestId = null): int { return 0; }
        public function writeSecurityEvent(string $eventType, ?string $userId, ?string $ipAddress = null, array $details = []): int { return 0; }
    };
    $events = new class implements \Illuminate\Contracts\Events\Dispatcher {
        public function listen($events, $listener = null): void {}
        public function hasListeners($eventName): bool { return false; }
        public function subscribe($subscriber): void {}
        public function until($event, $payload = []): mixed { return null; }
        public function dispatch($event, $payload = [], $halt = false): mixed { return null; }
        public function push($event, $payload = []): void {}
        public function flush($event): void {}
        public function forget($event): void {}
        public function forgetPushed(): void {}
    };

    return compact('forms', 'agg', 'form2307', 'pdf', 'audit', 'events');
}

it('1701Q rejects quarter 4 (folded into annual 1701)', function () {
    $s = stubRepos();
    $action = new GenerateForm1701Q(
        $s['forms'], $s['agg'], new IndividualIncomeTaxCalculator(),
        $s['pdf'], $s['audit'], $s['events'],
        new AggregateWithholdingCreditsForYear($s['form2307']),
        $s['form2307'], new OsdCalculator(),
    );

    expect(fn () => $action->execute(
        companyId: '018f0000-0000-7000-8000-000000000001',
        year: 2026, quarter: 4, actorId: '018f0000-0000-7000-8000-00000000aaaa',
    ))->toThrow(DomainException::class, 'Q4 is filed via the annual 1701');
});

it('1702Q rejects quarter 4 (folded into annual 1702-RT)', function () {
    $s = stubRepos();
    $action = new GenerateForm1702Q(
        $s['forms'], $s['agg'], new CorporateIncomeTaxCalculator(),
        $s['pdf'], $s['audit'], $s['events'],
        new AggregateWithholdingCreditsForYear($s['form2307']),
        $s['form2307'], new OsdCalculator(),
    );

    expect(fn () => $action->execute(
        companyId: '018f0000-0000-7000-8000-000000000001',
        year: 2026, quarter: 4, actorId: '018f0000-0000-7000-8000-00000000aaaa',
    ))->toThrow(DomainException::class, 'Q4 is filed via the annual 1702-RT');
});

it('1701Q rejects quarter 0 and negative quarters', function () {
    $s = stubRepos();
    $action = new GenerateForm1701Q(
        $s['forms'], $s['agg'], new IndividualIncomeTaxCalculator(),
        $s['pdf'], $s['audit'], $s['events'],
        new AggregateWithholdingCreditsForYear($s['form2307']),
        $s['form2307'], new OsdCalculator(),
    );

    expect(fn () => $action->execute(
        companyId: '018f0000-0000-7000-8000-000000000001',
        year: 2026, quarter: 0, actorId: '018f0000-0000-7000-8000-00000000aaaa',
    ))->toThrow(DomainException::class)
    ->and(fn () => $action->execute(
        companyId: '018f0000-0000-7000-8000-000000000001',
        year: 2026, quarter: -1, actorId: '018f0000-0000-7000-8000-00000000aaaa',
    ))->toThrow(DomainException::class);
});

it('1701Q refuses the OSD + 8% flat combined election (RR 8-2018 § 3)', function () {
    $s = stubRepos();
    $action = new GenerateForm1701Q(
        $s['forms'], $s['agg'], new IndividualIncomeTaxCalculator(),
        $s['pdf'], $s['audit'], $s['events'],
        new AggregateWithholdingCreditsForYear($s['form2307']),
        $s['form2307'], new OsdCalculator(),
    );

    expect(fn () => $action->execute(
        companyId: '018f0000-0000-7000-8000-000000000001',
        year: 2026, quarter: 1, actorId: '018f0000-0000-7000-8000-00000000aaaa',
        electFlat8Percent: true, useOsd: true,
    ))->toThrow(InvalidArgumentException::class, 'Cannot elect both OSD');
});
