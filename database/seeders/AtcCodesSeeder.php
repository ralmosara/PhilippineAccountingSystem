<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

final class AtcCodesSeeder extends Seeder
{
    public function run(): void
    {
        $template = json_decode(
            (string) file_get_contents(database_path('seed-data/atc-codes.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $now = now();
        $rows = [];

        foreach ($template['codes'] as $code) {
            $rows[] = [
                'id'             => Uuid::uuid5(Uuid::NAMESPACE_OID, 'pha.atc:'.$code['code'])->toString(),
                'code'           => $code['code'],
                'description'    => $code['description'],
                'rate'           => $code['rate'],
                'kind'           => $code['kind'],
                'effective_from' => '2018-01-01',                  // TRAIN era default
                'is_active'      => true,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }

        DB::table('tax.atc_codes')->upsert(
            $rows,
            ['code'],
            ['description', 'rate', 'kind', 'is_active', 'updated_at'],
        );

        $this->command?->info(sprintf('Seeded %d ATC codes.', count($rows)));
    }
}
