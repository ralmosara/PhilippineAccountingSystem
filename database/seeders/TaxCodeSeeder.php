<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * Seeds tax.tax_codes from BIR-published rates.
 * ATC codes (withholding) come in their own seeder when the Tax module
 * adds the atc_codes migration.
 */
final class TaxCodeSeeder extends Seeder
{
    public function run(): void
    {
        $template = json_decode(
            (string) file_get_contents(database_path('seed-data/vat-codes.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $now = now();
        $rows = [];

        foreach ($template['codes'] as $code) {
            $rows[] = [
                'id'              => Uuid::uuid5(Uuid::NAMESPACE_OID, 'pha.tax_codes:'.$code['code'])->toString(),
                'code'            => $code['code'],
                'name'            => $code['name'],
                'rate'            => $code['rate'],
                'kind'            => $code['kind'],
                'description'     => $code['description'],
                'effective_from'  => $code['effective_from'],
                'effective_to'    => $code['effective_to'] ?? null,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        DB::table('tax.tax_codes')->upsert(
            $rows,
            ['code'],
            ['name', 'rate', 'kind', 'description', 'effective_from', 'effective_to', 'updated_at'],
        );

        $this->command?->info(sprintf('Seeded %d tax codes.', count($rows)));
    }
}
