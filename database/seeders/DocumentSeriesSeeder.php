<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * Seeds BIR-required document series for the demo company:
 *   JV  — Journal Voucher
 *   CV  — Cash Voucher
 *   CRV — Cash Receipt Voucher
 *
 * Sales (OR / SI) series come from the Sales module's seeder.
 */
final class DocumentSeriesSeeder extends Seeder
{
    public function run(): void
    {
        $company = DB::table('identity.companies')->where('registered_name', 'ABC Trading Incorporated')->first();
        if (! $company) {
            return;
        }

        $branch = DB::table('identity.branches')->where('company_id', $company->id)->first();
        if (! $branch) {
            return;
        }

        $year = (int) now()->format('Y');
        $now = now();

        // BIR-registered series — both accounting (JV/CV/CRV) and sales (SI/OR)
        // share the same `accounting.document_series` table (one allocator
        // function works for everyone).
        $series = [
            // Accounting vouchers
            ['type' => 'JV',  'prefix' => "JV-{$year}-",  'start' => 1, 'end' => 999_999],
            ['type' => 'CV',  'prefix' => "CV-{$year}-",  'start' => 1, 'end' => 999_999],
            ['type' => 'CRV', 'prefix' => "CRV-{$year}-", 'start' => 1, 'end' => 999_999],
            // Sales documents — BIR ATP / ASTRA registered
            ['type' => 'SI',  'prefix' => "SI-{$year}-",  'start' => 1, 'end' => 999_999],
            ['type' => 'OR',  'prefix' => "OR-{$year}-",  'start' => 1, 'end' => 999_999],
            // Procurement documents
            ['type' => 'PO',  'prefix' => "PO-{$year}-",  'start' => 1, 'end' => 999_999],
        ];

        $rows = array_map(fn ($s) => [
            'id'            => Uuid::uuid5(Uuid::NAMESPACE_OID, "pha.doc_series:{$branch->id}:{$s['type']}:{$year}")->toString(),
            'company_id'    => $company->id,
            'branch_id'     => $branch->id,
            'document_type' => $s['type'],
            'prefix'        => $s['prefix'],
            'next_sequence' => $s['start'],
            'series_start'  => $s['start'],
            'series_end'    => $s['end'],
            'activated_at'  => $now,
            'is_active'     => true,
            'created_at'    => $now,
            'updated_at'    => $now,
        ], $series);

        DB::table('accounting.document_series')->upsert(
            $rows,
            ['company_id', 'branch_id', 'document_type', 'prefix'],
            ['series_end', 'is_active', 'updated_at'],
        );

        $this->command?->info(sprintf('Seeded %d document series (JV, CV, CRV) for %d.', count($rows), $year));
    }
}
