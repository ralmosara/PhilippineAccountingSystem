<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * Demo HR setup — 3 departments, 3 positions, 5 employees with active
 * compensation packages. Exercises the full payroll computation pipeline
 * (low/mid/high salary bands cover all TRAIN Law brackets).
 */
final class DepartmentsAndEmployeesSeeder extends Seeder
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

        // Departments
        $departments = [
            ['code' => 'FIN',  'name' => 'Finance & Accounting'],
            ['code' => 'OPS',  'name' => 'Operations'],
            ['code' => 'ADMIN','name' => 'Administration'],
        ];

        $deptIds = [];
        foreach ($departments as $i => $d) {
            $id = Uuid::uuid5(Uuid::NAMESPACE_OID, "pha.dept:{$company->id}:{$d['code']}")->toString();
            $deptIds[$d['code']] = $id;

            DB::table('hr.departments')->updateOrInsert(
                ['id' => $id],
                [
                    'company_id' => $company->id,
                    'code'       => $d['code'],
                    'name'       => $d['name'],
                    'path'       => strtolower($d['code']),
                    'is_active'  => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        // Positions
        $positions = [
            ['code' => 'ACCT', 'title' => 'Accountant',          'dept' => 'FIN'],
            ['code' => 'CSHR', 'title' => 'Cashier',             'dept' => 'OPS'],
            ['code' => 'OPMGR','title' => 'Operations Manager',  'dept' => 'OPS'],
            ['code' => 'ADMIN','title' => 'Admin Assistant',     'dept' => 'ADMIN'],
        ];

        $posIds = [];
        foreach ($positions as $p) {
            $id = Uuid::uuid5(Uuid::NAMESPACE_OID, "pha.pos:{$company->id}:{$p['code']}")->toString();
            $posIds[$p['code']] = $id;

            DB::table('hr.positions')->updateOrInsert(
                ['id' => $id],
                [
                    'company_id'    => $company->id,
                    'code'          => $p['code'],
                    'title'         => $p['title'],
                    'department_id' => $deptIds[$p['dept']],
                    'is_active'     => true,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ],
            );
        }

        // Employees — 5 across the salary spectrum
        $employees = [
            ['no' => 'EMP-000001', 'first' => 'Roberto',  'last' => 'Garcia',   'salary' => '50000', 'pos' => 'OPMGR', 'tin' => '111-111-111-000', 'sss' => '01-1111111-1', 'phic' => '01-111111111-1', 'hdmf' => '1111-1111-1111', 'mwe' => false],
            ['no' => 'EMP-000002', 'first' => 'Liza',     'last' => 'Tan',      'salary' => '35000', 'pos' => 'ACCT',  'tin' => '222-222-222-000', 'sss' => '02-2222222-2', 'phic' => '02-222222222-2', 'hdmf' => '2222-2222-2222', 'mwe' => false],
            ['no' => 'EMP-000003', 'first' => 'Carlos',   'last' => 'Reyes',    'salary' => '22000', 'pos' => 'ACCT',  'tin' => '333-333-333-000', 'sss' => '03-3333333-3', 'phic' => '03-333333333-3', 'hdmf' => '3333-3333-3333', 'mwe' => false],
            ['no' => 'EMP-000004', 'first' => 'Anna',     'last' => 'Lim',      'salary' => '18000', 'pos' => 'ADMIN', 'tin' => '444-444-444-000', 'sss' => '04-4444444-4', 'phic' => '04-444444444-4', 'hdmf' => '4444-4444-4444', 'mwe' => false],
            ['no' => 'EMP-000005', 'first' => 'Pedro',    'last' => 'Cruz',     'salary' => '13000', 'pos' => 'CSHR',  'tin' => null,              'sss' => '05-5555555-5', 'phic' => '05-555555555-5', 'hdmf' => '5555-5555-5555', 'mwe' => true],
        ];

        foreach ($employees as $i => $e) {
            $empId = Uuid::uuid5(Uuid::NAMESPACE_OID, "pha.employee:{$company->id}:{$e['no']}")->toString();

            DB::table('hr.employees')->updateOrInsert(
                ['id' => $empId],
                [
                    'company_id'              => $company->id,
                    'employee_no'             => $e['no'],
                    'tin_encrypted'           => $e['tin']  ? Crypt::encryptString($e['tin'])  : null,
                    'sss_no_encrypted'        => $e['sss']  ? Crypt::encryptString($e['sss'])  : null,
                    'philhealth_no_encrypted' => $e['phic'] ? Crypt::encryptString($e['phic']) : null,
                    'pagibig_no_encrypted'    => $e['hdmf'] ? Crypt::encryptString($e['hdmf']) : null,
                    'first_name'              => $e['first'],
                    'last_name'               => $e['last'],
                    'hired_on'                => '2020-01-01',
                    'regularized_on'          => '2020-07-01',
                    'employment_status'       => 'regular',
                    'position_id'             => $posIds[$e['pos']],
                    'department_id'           => $deptIds[match ($e['pos']) {
                        'ACCT'         => 'FIN',
                        'CSHR', 'OPMGR' => 'OPS',
                        'ADMIN'        => 'ADMIN',
                    }],
                    'is_active'               => true,
                    'created_at'              => $now,
                    'updated_at'              => $now,
                ],
            );

            // Active compensation package
            $pkgId = Uuid::uuid5(Uuid::NAMESPACE_OID, "pha.comp:{$empId}:current")->toString();
            DB::table('payroll.compensation_packages')->updateOrInsert(
                ['id' => $pkgId],
                [
                    'employee_id'             => $empId,
                    'effective_from'          => '2025-01-01',
                    'basic_monthly'           => $e['salary'],
                    'basic_daily'             => bcdiv($e['salary'], '22', 2),
                    'working_days_per_month'  => 22,
                    'hours_per_day'           => 8,
                    'hourly_rate'             => bcdiv(bcdiv($e['salary'], '22', 4), '8', 4),
                    'is_minimum_wage_earner'  => $e['mwe'],
                    'created_at'              => $now,
                    'updated_at'              => $now,
                ],
            );
        }

        $this->command?->info(sprintf(
            'Seeded %d departments, %d positions, %d employees with active compensation packages.',
            count($departments), count($positions), count($employees),
        ));
    }
}
