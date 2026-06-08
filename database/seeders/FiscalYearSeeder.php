<?php

declare(strict_types=1);

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * Seeds the current fiscal year + 12 monthly periods for the demo company.
 * Idempotent — re-running uses upsert.
 */
final class FiscalYearSeeder extends Seeder
{
    public function run(): void
    {
        $company = DB::table('identity.companies')->where('registered_name', 'ABC Trading Incorporated')->first();
        if (! $company) {
            $this->command?->warn('FiscalYearSeeder: demo company not found; skipping.');
            return;
        }

        $year = (int) now()->format('Y');
        $fiscalYearId = Uuid::uuid5(Uuid::NAMESPACE_OID, "pha.fiscal_year:{$company->id}:{$year}")->toString();

        DB::table('accounting.fiscal_years')->updateOrInsert(
            ['id' => $fiscalYearId],
            [
                'company_id'  => $company->id,
                'year_number' => $year,
                'starts_on'   => "{$year}-01-01",
                'ends_on'     => "{$year}-12-31",
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        );

        // 12 monthly periods
        $periodRows = [];
        for ($month = 1; $month <= 12; $month++) {
            $start = CarbonImmutable::create($year, $month, 1);
            $end   = $start->endOfMonth();

            $periodRows[] = [
                'id'             => Uuid::uuid5(Uuid::NAMESPACE_OID, "pha.fiscal_period:{$fiscalYearId}:{$month}")->toString(),
                'fiscal_year_id' => $fiscalYearId,
                'period_number'  => $month,
                'label'          => $start->format('M Y'),
                'starts_on'      => $start->toDateString(),
                'ends_on'        => $end->toDateString(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }

        DB::table('accounting.fiscal_periods')->upsert(
            $periodRows,
            ['fiscal_year_id', 'period_number'],
            ['label', 'starts_on', 'ends_on', 'updated_at'],
        );

        $this->command?->info(sprintf('Seeded fiscal year %d with 12 monthly periods.', $year));
    }
}
