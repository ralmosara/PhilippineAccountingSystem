<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * Seeds a handful of demo customers for the demo company:
 *   - Regular VAT-registered corporate customer
 *   - Senior citizen individual (qualifies for 20% discount + VAT exempt)
 *   - PWD individual (same treatment as senior)
 *   - Government agency (5% withheld VAT applies)
 *   - VAT-exempt small business
 */
final class CustomersSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('CustomersSeeder skipped in production.');
            return;
        }

        $company = DB::table('identity.companies')
            ->where('registered_name', 'ABC Trading Incorporated')
            ->first();

        if (! $company) {
            return;
        }

        $now = now();
        $customers = [
            [
                'name' => 'XYZ Manufacturing Corp.',
                'tin'  => '012-345-678-000',
                'flags' => [
                    'is_vat_registered' => true,
                    'is_government'     => false,
                    'is_senior_citizen' => false,
                    'is_pwd'            => false,
                ],
                'email' => 'ap@xyzmfg.example',
                'payment_terms_days' => 30,
            ],
            [
                'name' => 'Maria Santos',
                'tin'  => null,
                'flags' => [
                    'is_vat_registered' => false,
                    'is_government'     => false,
                    'is_senior_citizen' => true,
                    'is_pwd'            => false,
                ],
                'email' => 'maria.santos@example.com',
                'payment_terms_days' => 0,
            ],
            [
                'name' => 'Juan Dela Cruz (PWD)',
                'tin'  => null,
                'flags' => [
                    'is_vat_registered' => false,
                    'is_government'     => false,
                    'is_senior_citizen' => false,
                    'is_pwd'            => true,
                ],
                'email' => 'juan.delacruz@example.com',
                'payment_terms_days' => 0,
            ],
            [
                'name' => 'Department of Public Works and Highways',
                'tin'  => '000-000-000-005',
                'flags' => [
                    'is_vat_registered' => true,
                    'is_government'     => true,        // → 5% withheld VAT applies
                    'is_senior_citizen' => false,
                    'is_pwd'            => false,
                ],
                'email' => 'finance@dpwh.gov.ph.example',
                'payment_terms_days' => 60,
            ],
            [
                'name' => 'Sari-Sari Store',
                'tin'  => '987-654-321-000',
                'flags' => [
                    'is_vat_registered' => false,       // → percentage tax 3%
                    'is_government'     => false,
                    'is_senior_citizen' => false,
                    'is_pwd'            => false,
                ],
                'email' => null,
                'payment_terms_days' => 0,
            ],
        ];

        $rows = [];
        foreach ($customers as $i => $c) {
            $rows[] = [
                'id'                 => Uuid::uuid5(Uuid::NAMESPACE_OID, "pha.customer:{$company->id}:{$i}")->toString(),
                'company_id'         => $company->id,
                'customer_no'        => sprintf('CUST-%06d', $i + 1),
                'registered_name'    => $c['name'],
                'tin'                => $c['tin'],
                'is_vat_registered'  => $c['flags']['is_vat_registered'],
                'is_government'      => $c['flags']['is_government'],
                'is_senior_citizen'  => $c['flags']['is_senior_citizen'],
                'is_pwd'             => $c['flags']['is_pwd'],
                'email'              => $c['email'],
                'payment_terms_days' => $c['payment_terms_days'],
                'default_currency'   => 'PHP',
                'is_active'          => true,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }

        DB::table('sales.customers')->upsert(
            $rows,
            ['company_id', 'customer_no'],
            ['registered_name', 'tin', 'is_vat_registered', 'is_government',
             'is_senior_citizen', 'is_pwd', 'email', 'payment_terms_days', 'updated_at'],
        );

        $this->command?->info(sprintf('Seeded %d demo customers.', count($rows)));
    }
}
