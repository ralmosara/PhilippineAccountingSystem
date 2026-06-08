<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain\Entities;

use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;

final class CashFlowStatement
{
    /** @var list<array{description: string, amount: string}> */
    public array $operatingActivities = [];

    /** @var list<array{description: string, amount: string}> */
    public array $investingActivities = [];

    /** @var list<array{description: string, amount: string}> */
    public array $financingActivities = [];

    public string $totalOperating = '0.00';
    public string $totalInvesting = '0.00';
    public string $totalFinancing = '0.00';

    public string $beginningCash = '0.00';
    public string $endingCash    = '0.00';

    public function __construct(
        public readonly string $companyId,
        public readonly ReportPeriod $period,
    ) {
    }

    /** @param array{description: string, amount: string} $line */
    public function addOperating(array $line): void
    {
        $this->operatingActivities[] = $line;
        $this->totalOperating = bcadd($this->totalOperating, $line['amount'], 2);
    }

    /** @param array{description: string, amount: string} $line */
    public function addInvesting(array $line): void
    {
        $this->investingActivities[] = $line;
        $this->totalInvesting = bcadd($this->totalInvesting, $line['amount'], 2);
    }

    /** @param array{description: string, amount: string} $line */
    public function addFinancing(array $line): void
    {
        $this->financingActivities[] = $line;
        $this->totalFinancing = bcadd($this->totalFinancing, $line['amount'], 2);
    }

    public function netChange(): string
    {
        return bcadd(bcadd($this->totalOperating, $this->totalInvesting, 2), $this->totalFinancing, 2);
    }

    /** Sanity check: beginning + net change should equal ending cash. */
    public function reconciles(): bool
    {
        $expected = bcadd($this->beginningCash, $this->netChange(), 2);
        return bccomp($expected, $this->endingCash, 2) === 0;
    }
}
