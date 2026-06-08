<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Services;

use App\Modules\Accounting\Domain\ValueObjects\Money;

/**
 * Computes SSS / PhilHealth / Pag-IBIG deductions from a monthly basic salary.
 *
 * The rate-table data is loaded by the Application layer from
 * payroll.{sss_rate_tables, philhealth_rate_tables, pagibig_rate_tables}
 * and passed in here as plain arrays — keeps the domain service free of
 * Eloquent/DB concerns.
 */
final readonly class StatutoryDeductionCalculator
{
    /**
     * SSS — bracket-based monthly contribution.
     *
     * @param  list<array{msc_floor: string, msc_ceiling: string, ee_amount: string, er_amount: string}>  $brackets
     * @return array{ee: Money, er: Money, msc: string}
     */
    public function computeSss(Money $monthlyBasic, array $brackets): array
    {
        $monthly = $monthlyBasic->amount;

        // Below floor → first bracket
        // Above ceiling → last bracket
        $matched = $brackets[0];
        foreach ($brackets as $bracket) {
            if (bccomp($monthly, $bracket['msc_floor'], 2) >= 0
                && bccomp($monthly, $bracket['msc_ceiling'], 2) <= 0) {
                $matched = $bracket;
                break;
            }
            if (bccomp($monthly, $bracket['msc_ceiling'], 2) > 0) {
                $matched = $bracket;
            }
        }

        return [
            'ee'  => Money::php($matched['ee_amount']),
            'er'  => Money::php($matched['er_amount']),
            'msc' => $matched['msc_floor'],
        ];
    }

    /**
     * PhilHealth — flat premium rate split 50/50 between EE and ER.
     *
     * @param  array{premium_rate: string, salary_floor: string, salary_ceiling: string}  $config
     * @return array{ee: Money, er: Money, total: Money}
     */
    public function computePhilhealth(Money $monthlyBasic, array $config): array
    {
        // Clamp salary into [floor, ceiling]
        $base = $monthlyBasic->amount;
        if (bccomp($base, $config['salary_floor'], 2) < 0) {
            $base = $config['salary_floor'];
        } elseif (bccomp($base, $config['salary_ceiling'], 2) > 0) {
            $base = $config['salary_ceiling'];
        }

        $total = bcmul($base, $config['premium_rate'], 2);
        $half  = bcdiv($total, '2', 2);

        return [
            'ee'    => Money::php($half),
            'er'    => Money::php($half),
            'total' => Money::php($total),
        ];
    }

    /**
     * Pag-IBIG — two-tier rate with salary cap.
     *
     * @param  array{ee_rate_low: string, ee_rate_high: string, er_rate: string, low_threshold: string, salary_cap: string}  $config
     * @return array{ee: Money, er: Money}
     */
    public function computePagibig(Money $monthlyBasic, array $config): array
    {
        // Cap the salary at salary_cap for contribution computation
        $base = $monthlyBasic->amount;
        if (bccomp($base, $config['salary_cap'], 2) > 0) {
            $base = $config['salary_cap'];
        }

        $eeRate = bccomp($monthlyBasic->amount, $config['low_threshold'], 2) <= 0
            ? $config['ee_rate_low']
            : $config['ee_rate_high'];

        return [
            'ee' => Money::php(bcmul($base, $eeRate, 2)),
            'er' => Money::php(bcmul($base, $config['er_rate'], 2)),
        ];
    }
}
