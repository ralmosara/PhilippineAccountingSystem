<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain\Services;

use App\Modules\Reporting\Domain\Entities\TrialBalance;
use App\Modules\Reporting\Domain\Entities\TrialBalanceLine;
use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;

/**
 * Assembles a TrialBalance from raw aggregated rows.
 *
 * Input rows come pre-aggregated from `ReportDataAggregator` (one row per
 * postable account). This service computes the signed balance per the
 * account's normal_balance and accumulates the totals.
 */
final readonly class TrialBalanceBuilder
{
    /**
     * @param  list<array{
     *     account_id: string,
     *     account_code: string,
     *     account_name: string,
     *     account_type: string,
     *     normal_balance: string,
     *     total_debit: string,
     *     total_credit: string,
     * }>  $rows
     */
    public function build(string $companyId, ReportPeriod $period, array $rows): TrialBalance
    {
        $tb = new TrialBalance($companyId, $period);

        foreach ($rows as $row) {
            // Skip accounts with zero activity AND zero balance
            if (bccomp($row['total_debit'], '0', 2) === 0
                && bccomp($row['total_credit'], '0', 2) === 0) {
                continue;
            }

            // Signed balance: debit - credit for debit-normal; credit - debit for credit-normal
            $rawBalance = bcsub($row['total_debit'], $row['total_credit'], 2);
            $signedBalance = $row['normal_balance'] === 'debit'
                ? $rawBalance
                : bcmul($rawBalance, '-1', 2);

            $tb->addLine(new TrialBalanceLine(
                accountId:     $row['account_id'],
                accountCode:   $row['account_code'],
                accountName:   $row['account_name'],
                accountType:   $row['account_type'],
                normalBalance: $row['normal_balance'],
                debit:         $row['total_debit'],
                credit:        $row['total_credit'],
                balance:       $signedBalance,
            ));
        }

        return $tb;
    }
}
