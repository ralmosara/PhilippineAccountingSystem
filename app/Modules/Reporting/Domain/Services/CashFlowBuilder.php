<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain\Services;

use App\Modules\Reporting\Domain\Entities\CashFlowStatement;
use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;

/**
 * Assembles a CashFlowStatement from the aggregator's classified lines.
 * Uses the **direct method** with type-based categorization heuristics.
 */
final readonly class CashFlowBuilder
{
    /**
     * @param array{
     *     operating: list<array{description: string, amount: string}>,
     *     investing: list<array{description: string, amount: string}>,
     *     financing: list<array{description: string, amount: string}>,
     *     beginning_cash: string,
     *     ending_cash: string,
     * } $data
     */
    public function build(string $companyId, ReportPeriod $period, array $data): CashFlowStatement
    {
        $cf = new CashFlowStatement($companyId, $period);
        $cf->beginningCash = $data['beginning_cash'];
        $cf->endingCash    = $data['ending_cash'];

        foreach ($data['operating'] as $line) $cf->addOperating($line);
        foreach ($data['investing'] as $line) $cf->addInvesting($line);
        foreach ($data['financing'] as $line) $cf->addFinancing($line);

        return $cf;
    }
}
