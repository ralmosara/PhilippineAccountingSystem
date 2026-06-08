<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Persistence;

use App\Modules\Payroll\Application\Contracts\CompensationProviderContract;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;

final readonly class EloquentCompensationProvider implements CompensationProviderContract
{
    public function __construct(private ConnectionInterface $db)
    {
    }

    public function findActive(string $employeeId, DateTimeImmutable $asOf): ?array
    {
        $pkg = $this->db->selectOne(<<<'SQL'
            SELECT id, basic_monthly, is_minimum_wage_earner
            FROM payroll.compensation_packages
            WHERE employee_id = ?::uuid
              AND effective_from <= ?::date
              AND (effective_to IS NULL OR effective_to >= ?::date)
            ORDER BY effective_from DESC
            LIMIT 1
        SQL, [$employeeId, $asOf->format('Y-m-d'), $asOf->format('Y-m-d')]);

        if (! $pkg) {
            return null;
        }

        // Aggregate allowance components
        $allowances = $this->db->selectOne(<<<'SQL'
            SELECT
                COALESCE(SUM(CASE WHEN component_type = 'allowance' AND is_taxable     THEN amount END), 0) AS taxable,
                COALESCE(SUM(CASE WHEN component_type = 'allowance' AND NOT is_taxable THEN amount END), 0) AS nontaxable
            FROM payroll.compensation_components
            WHERE compensation_package_id = ?::uuid
              AND recurring = true
        SQL, [$pkg->id]);

        return [
            'basic_monthly'          => (string) $pkg->basic_monthly,
            'taxable_allowances'     => (string) ($allowances->taxable ?? '0'),
            'nontaxable_allowances'  => (string) ($allowances->nontaxable ?? '0'),
            'is_minimum_wage_earner' => (bool) $pkg->is_minimum_wage_earner,
        ];
    }
}
