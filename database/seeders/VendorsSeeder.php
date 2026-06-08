<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * Demo vendors with a mix of withholding profiles to exercise the
 * 2307 issuance pipeline end-to-end.
 */
final class VendorsSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        $company = DB::table('identity.companies')
            ->where('registered_name', 'ABC Trading Incorporated')
            ->first();

        if (! $company) {
            return;
        }

        $now = now();
        $vendors = [
            [
                'name' => 'Acme Office Supplies Corp.',
                'tin'  => '111-222-333-000',
                'is_vat_registered'        => true,
                'default_atc_code'         => 'WC010',          // top WT agent — goods 1%
                'default_withholding_rate' => '0.0100',
                'payment_terms_days'       => 30,
            ],
            [
                'name' => 'Globe Telecom (utilities)',
                'tin'  => '222-333-444-000',
                'is_vat_registered'        => true,
                'default_atc_code'         => null,             // utilities not subject to EWT
                'default_withholding_rate' => null,
                'payment_terms_days'       => 15,
            ],
            [
                'name' => 'Atty. Reyes Law Office',
                'tin'  => '333-444-555-000',
                'is_vat_registered'        => false,
                'default_atc_code'         => 'WI010',          // professional fee individual 5%
                'default_withholding_rate' => '0.0500',
                'payment_terms_days'       => 30,
            ],
            [
                'name' => 'Makati Office Realty Inc.',
                'tin'  => '444-555-666-000',
                'is_vat_registered'        => true,
                'default_atc_code'         => 'WI070',          // rental real property 5%
                'default_withholding_rate' => '0.0500',
                'payment_terms_days'       => 5,
            ],
            [
                'name' => 'PrintWorks Contractor Corp.',
                'tin'  => '555-666-777-000',
                'is_vat_registered'        => true,
                'default_atc_code'         => 'WC156',          // contractor 2%
                'default_withholding_rate' => '0.0200',
                'payment_terms_days'       => 45,
            ],
        ];

        $rows = [];
        foreach ($vendors as $i => $v) {
            $rows[] = [
                'id'                       => Uuid::uuid5(Uuid::NAMESPACE_OID, "pha.vendor:{$company->id}:{$i}")->toString(),
                'company_id'               => $company->id,
                'vendor_no'                => sprintf('VEN-%06d', $i + 1),
                'registered_name'          => $v['name'],
                'tin'                      => $v['tin'],
                'is_vat_registered'        => $v['is_vat_registered'],
                'is_government_supplier'   => false,
                'is_top_withholding_agent' => false,
                'default_atc_code'         => $v['default_atc_code'],
                'default_withholding_rate' => $v['default_withholding_rate'],
                'payment_terms_days'       => $v['payment_terms_days'],
                'default_currency'         => 'PHP',
                'is_active'                => true,
                'created_at'               => $now,
                'updated_at'               => $now,
            ];
        }

        DB::table('procurement.vendors')->upsert(
            $rows,
            ['company_id', 'vendor_no'],
            ['registered_name', 'tin', 'default_atc_code', 'default_withholding_rate',
             'payment_terms_days', 'updated_at'],
        );

        $this->command?->info(sprintf('Seeded %d demo vendors.', count($rows)));
    }
}
