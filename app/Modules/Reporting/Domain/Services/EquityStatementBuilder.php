<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain\Services;

use App\Modules\Reporting\Domain\Entities\EquityStatement;
use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;

final readonly class EquityStatementBuilder
{
    /**
     * @param array{
     *     accounts: list<array{
     *         account_id: string,
     *         account_code: string,
     *         account_name: string,
     *         beginning_balance: string,
     *         movement: string,
     *         ending_balance: string,
     *     }>,
     *     net_income_for_period: string,
     * } $data
     */
    public function build(string $companyId, ReportPeriod $period, array $data): EquityStatement
    {
        $es = new EquityStatement($companyId, $period);

        foreach ($data['accounts'] as $row) {
            $es->addAccount($row);
        }

        $es->setNetIncome($data['net_income_for_period']);

        return $es;
    }
}
