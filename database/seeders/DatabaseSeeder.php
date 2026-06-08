<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Identity / RBAC
            IdentityRolesAndPermissionsSeeder::class,
            DemoCompanySeeder::class,

            // Tax (codes need to exist before journal_lines reference them)
            TaxCodeSeeder::class,
            AtcCodesSeeder::class,

            // Accounting
            ChartOfAccountsSeeder::class,
            FiscalYearSeeder::class,
            DocumentSeriesSeeder::class,

            // Sales
            CustomersSeeder::class,

            // Procurement
            VendorsSeeder::class,

            // Payroll — rate tables first, then HR/employees, then compensation packages
            StatutoryRatesSeeder::class,
            DepartmentsAndEmployeesSeeder::class,

            // Inventory — UoM (global), categories, warehouse, items
            InventorySeeder::class,
        ]);
    }
}
