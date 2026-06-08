<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * Seeds 2026 statutory rate tables — SSS, PhilHealth, Pag-IBIG, and TRAIN Law
 * BIR withholding tax brackets (monthly + semi-monthly).
 *
 * In production, update these when the agencies issue new circulars and
 * call `php artisan db:seed --class=StatutoryRatesSeeder` to refresh.
 */
final class StatutoryRatesSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(
            (string) file_get_contents(database_path('seed-data/statutory-rates-2026.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $now = now();

        // SSS
        DB::table('payroll.sss_rate_tables')->upsert(
            [[
                'id'             => Uuid::uuid5(Uuid::NAMESPACE_OID, 'pha.sss:'.$data['sss']['effective_from'])->toString(),
                'effective_from' => $data['sss']['effective_from'],
                'msc_floor'      => $data['sss']['msc_floor'],
                'msc_ceiling'    => $data['sss']['msc_ceiling'],
                'brackets'       => json_encode($data['sss']['brackets'], JSON_THROW_ON_ERROR),
                'created_at'     => $now,
                'updated_at'     => $now,
            ]],
            ['id'],
            ['msc_floor', 'msc_ceiling', 'brackets', 'updated_at'],
        );

        // PhilHealth
        DB::table('payroll.philhealth_rate_tables')->upsert(
            [[
                'id'             => Uuid::uuid5(Uuid::NAMESPACE_OID, 'pha.phic:'.$data['philhealth']['effective_from'])->toString(),
                'effective_from' => $data['philhealth']['effective_from'],
                'premium_rate'   => $data['philhealth']['premium_rate'],
                'salary_floor'   => $data['philhealth']['salary_floor'],
                'salary_ceiling' => $data['philhealth']['salary_ceiling'],
                'created_at'     => $now,
                'updated_at'     => $now,
            ]],
            ['id'],
            ['premium_rate', 'salary_floor', 'salary_ceiling', 'updated_at'],
        );

        // Pag-IBIG
        DB::table('payroll.pagibig_rate_tables')->upsert(
            [[
                'id'             => Uuid::uuid5(Uuid::NAMESPACE_OID, 'pha.hdmf:'.$data['pagibig']['effective_from'])->toString(),
                'effective_from' => $data['pagibig']['effective_from'],
                'ee_rate_low'    => $data['pagibig']['ee_rate_low'],
                'ee_rate_high'   => $data['pagibig']['ee_rate_high'],
                'er_rate'        => $data['pagibig']['er_rate'],
                'low_threshold'  => $data['pagibig']['low_threshold'],
                'salary_cap'     => $data['pagibig']['salary_cap'],
                'created_at'     => $now,
                'updated_at'     => $now,
            ]],
            ['id'],
            ['ee_rate_low', 'ee_rate_high', 'er_rate', 'low_threshold', 'salary_cap', 'updated_at'],
        );

        // BIR — monthly + semi-monthly
        $birRows = [];
        foreach (['monthly', 'semimonthly'] as $freq) {
            $key = "bir_tax_table_{$freq}";
            $birRows[] = [
                'id'             => Uuid::uuid5(Uuid::NAMESPACE_OID, "pha.bir:{$freq}:".$data[$key]['effective_from'])->toString(),
                'effective_from' => $data[$key]['effective_from'],
                'frequency'      => $freq,
                'brackets'       => json_encode($data[$key]['brackets'], JSON_THROW_ON_ERROR),
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }
        DB::table('payroll.bir_tax_tables')->upsert(
            $birRows,
            ['id'],
            ['brackets', 'updated_at'],
        );

        $this->command?->info('Seeded 2026 statutory rates (SSS, PhilHealth, Pag-IBIG, BIR monthly + semimonthly).');
    }
}
