<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Persistence;

use App\Modules\Payroll\Application\Contracts\StatutoryRateProviderContract;
use App\Modules\Payroll\Domain\ValueObjects\PayrollFrequency;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

final readonly class EloquentStatutoryRateProvider implements StatutoryRateProviderContract
{
    public function __construct(private ConnectionInterface $db)
    {
    }

    public function ratesEffectiveOn(DateTimeImmutable $date, PayrollFrequency $frequency): array
    {
        $cacheKey = sprintf('payroll.rates.%s.%s', $date->format('Y-m'), $frequency->value);

        return Cache::remember(
            key: $cacheKey,
            ttl: 86400,                              // 1 day; rates don't change mid-month
            callback: fn () => $this->loadRates($date, $frequency),
        );
    }

    /** @return array<string, mixed> */
    private function loadRates(DateTimeImmutable $date, PayrollFrequency $frequency): array
    {
        $sss = $this->db->selectOne(<<<'SQL'
            SELECT brackets FROM payroll.sss_rate_tables
            WHERE effective_from <= ?::date AND (effective_to IS NULL OR effective_to >= ?::date)
            ORDER BY effective_from DESC LIMIT 1
        SQL, [$date->format('Y-m-d'), $date->format('Y-m-d')]);

        $phic = $this->db->selectOne(<<<'SQL'
            SELECT premium_rate, salary_floor, salary_ceiling FROM payroll.philhealth_rate_tables
            WHERE effective_from <= ?::date AND (effective_to IS NULL OR effective_to >= ?::date)
            ORDER BY effective_from DESC LIMIT 1
        SQL, [$date->format('Y-m-d'), $date->format('Y-m-d')]);

        $hdmf = $this->db->selectOne(<<<'SQL'
            SELECT ee_rate_low, ee_rate_high, er_rate, low_threshold, salary_cap FROM payroll.pagibig_rate_tables
            WHERE effective_from <= ?::date AND (effective_to IS NULL OR effective_to >= ?::date)
            ORDER BY effective_from DESC LIMIT 1
        SQL, [$date->format('Y-m-d'), $date->format('Y-m-d')]);

        $bir = $this->db->selectOne(<<<'SQL'
            SELECT brackets FROM payroll.bir_tax_tables
            WHERE frequency = ?::text AND effective_from <= ?::date
              AND (effective_to IS NULL OR effective_to >= ?::date)
            ORDER BY effective_from DESC LIMIT 1
        SQL, [$frequency->value, $date->format('Y-m-d'), $date->format('Y-m-d')]);

        if (! $sss || ! $phic || ! $hdmf || ! $bir) {
            throw new RuntimeException(
                "Missing statutory rate table effective on {$date->format('Y-m-d')} for frequency {$frequency->value}. "
                ."Run StatutoryRatesSeeder."
            );
        }

        return [
            'sss_brackets' => is_array($sss->brackets) ? $sss->brackets : json_decode((string) $sss->brackets, true),
            'philhealth'   => [
                'premium_rate'   => (string) $phic->premium_rate,
                'salary_floor'   => (string) $phic->salary_floor,
                'salary_ceiling' => (string) $phic->salary_ceiling,
            ],
            'pagibig'      => [
                'ee_rate_low'    => (string) $hdmf->ee_rate_low,
                'ee_rate_high'   => (string) $hdmf->ee_rate_high,
                'er_rate'        => (string) $hdmf->er_rate,
                'low_threshold'  => (string) $hdmf->low_threshold,
                'salary_cap'     => (string) $hdmf->salary_cap,
            ],
            'bir_brackets' => is_array($bir->brackets) ? $bir->brackets : json_decode((string) $bir->brackets, true),
        ];
    }
}
