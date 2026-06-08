<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;

/**
 * Seeds a demo company "ABC Trading Inc." with one head-office branch and
 * five users covering the standard role spread. Idempotent — safe to run
 * multiple times in dev.
 *
 * Disabled in production via the seeder's APP_ENV check.
 */
final class DemoCompanySeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DemoCompanySeeder skipped in production.');
            return;
        }

        $companyId = '018f0000-0000-7000-8000-000000000001';
        $branchId  = '018f0000-0000-7000-8000-000000000002';

        // Company
        DB::table('identity.companies')->updateOrInsert(
            ['id' => $companyId],
            [
                'tin'              => '000-123-456-000',
                'rdo_code'         => '039',
                'registered_name'  => 'ABC Trading Incorporated',
                'trade_name'       => 'ABC Trading',
                'taxpayer_type'    => 'large',
                'vat_status'       => 'vat',
                'address'          => '123 Ayala Avenue, Makati City, NCR',
                'telephone'        => '+63-2-8123-4567',
                'email'            => 'finance@abctrading.example',
                'registered_on'    => '2010-01-15',
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
        );

        // Head-office branch
        DB::table('identity.branches')->updateOrInsert(
            ['id' => $branchId],
            [
                'company_id'      => $companyId,
                'code'            => 'HO',
                'name'            => 'Head Office — Makati',
                'bir_branch_code' => '00-001',
                'address'         => '123 Ayala Avenue, Makati City, NCR',
                'is_head_office'  => true,
                'is_active'       => true,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
        );

        // Five demo users — one per critical role
        $users = [
            ['email' => 'admin@pha.local',      'name' => 'System Administrator', 'role' => 'Admin'],
            ['email' => 'accountant@pha.local', 'name' => 'Maria Cruz',           'role' => 'Accountant'],
            ['email' => 'approver@pha.local',   'name' => 'Jose Santos',          'role' => 'Approver'],
            ['email' => 'auditor@pha.local',    'name' => 'Ana Reyes',            'role' => 'Auditor'],
            ['email' => 'cashier@pha.local',    'name' => 'Pedro Dela Cruz',      'role' => 'Cashier'],
        ];

        foreach ($users as $i => $u) {
            $user = UserModel::firstOrCreate(
                ['email' => $u['email']],
                [
                    'id'                => sprintf('018f0000-0000-7000-8000-00000000010%d', $i + 1),
                    'company_id'        => $companyId,
                    'password_hash'     => Hash::make('Mypass123'),
                    'full_name'         => $u['name'],
                    'employee_no'       => sprintf('EMP-%06d', $i + 1),
                    'mfa_enabled'       => false,
                    'email_verified_at' => now(),
                    'is_active'         => true,
                ],
            );

            $user->syncRoles($u['role']);

            // Grant access to head-office branch
            DB::table('identity.user_branch_scopes')->updateOrInsert(
                ['user_id' => $user->id, 'branch_id' => $branchId],
                ['can_post' => $u['role'] !== 'Viewer', 'granted_at' => now()],
            );
        }

        $this->command?->info(sprintf(
            'Seeded demo company "ABC Trading Inc." with 1 branch and %d users (password: Mypass123).',
            count($users),
        ));
    }
}
