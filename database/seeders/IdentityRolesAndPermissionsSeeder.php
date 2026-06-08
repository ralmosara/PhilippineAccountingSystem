<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the 8 standard roles and their starter permission sets.
 *
 * Roles (system-managed; can't be deleted via UI):
 *   Admin, Accountant, Approver, Auditor, Cashier, HR, Payroll, Viewer
 */
final class IdentityRolesAndPermissionsSeeder extends Seeder
{
    /** @var array<string, array{display: string, description: string}> */
    private const ROLES = [
        'Admin'      => ['display' => 'Administrator',  'description' => 'Full access; can configure users, roles, tax tables.'],
        'Accountant' => ['display' => 'Accountant',     'description' => 'Day-to-day bookkeeping: journals, AR, AP, tax form generation.'],
        'Approver'   => ['display' => 'Approver',       'description' => 'Posts journals, approves payroll, files BIR forms. MFA required.'],
        'Auditor'    => ['display' => 'Auditor',        'description' => 'Read-only access to all financial data + audit chain. MFA required.'],
        'Cashier'    => ['display' => 'Cashier',        'description' => 'POS, OR/SI issuance, sales transactions.'],
        'HR'         => ['display' => 'HR Officer',     'description' => 'Employee records, attendance, leave.'],
        'Payroll'    => ['display' => 'Payroll Officer','description' => 'Compensation packages, payroll runs (pre-approval).'],
        'Viewer'     => ['display' => 'Viewer',         'description' => 'Read-only access to financial reports.'],
    ];

    /** @var array<int, array{name: string, module: string, display: string}> */
    private const PERMISSIONS = [
        // Identity
        ['name' => 'identity.users.view',      'module' => 'identity', 'display' => 'View users'],
        ['name' => 'identity.users.create',    'module' => 'identity', 'display' => 'Create users'],
        ['name' => 'identity.users.update',    'module' => 'identity', 'display' => 'Update users'],
        ['name' => 'identity.users.delete',    'module' => 'identity', 'display' => 'Disable users'],

        // Accounting
        ['name' => 'accounting.journals.view',    'module' => 'accounting', 'display' => 'View journal entries'],
        ['name' => 'accounting.journals.create',  'module' => 'accounting', 'display' => 'Create journal entries (draft)'],
        ['name' => 'accounting.journals.post',    'module' => 'accounting', 'display' => 'Post journal entries'],
        ['name' => 'accounting.journals.reverse', 'module' => 'accounting', 'display' => 'Reverse journal entries'],
        ['name' => 'accounting.coa.manage',       'module' => 'accounting', 'display' => 'Manage Chart of Accounts'],
        ['name' => 'accounting.periods.lock',     'module' => 'accounting', 'display' => 'Lock fiscal periods'],

        // Sales
        ['name' => 'sales.invoices.view',     'module' => 'sales', 'display' => 'View sales invoices'],
        ['name' => 'sales.invoices.create',   'module' => 'sales', 'display' => 'Create sales invoices'],
        ['name' => 'sales.invoices.void',     'module' => 'sales', 'display' => 'Void sales invoices'],
        ['name' => 'sales.or.issue',          'module' => 'sales', 'display' => 'Issue Official Receipts'],
        ['name' => 'sales.pos.transact',      'module' => 'sales', 'display' => 'Process POS transactions'],

        // Procurement
        ['name' => 'procurement.bills.view',   'module' => 'procurement', 'display' => 'View vendor bills'],
        ['name' => 'procurement.bills.create', 'module' => 'procurement', 'display' => 'Create vendor bills'],
        ['name' => 'procurement.bills.post',   'module' => 'procurement', 'display' => 'Post vendor bills'],

        // Inventory
        ['name' => 'inventory.items.view',       'module' => 'inventory', 'display' => 'View items + on-hand quantities'],
        ['name' => 'inventory.movements.view',   'module' => 'inventory', 'display' => 'View stock movement register'],
        ['name' => 'inventory.list.generate',    'module' => 'inventory', 'display' => 'Generate annual Inventory List (BIR submission)'],

        // Tax / BIR
        ['name' => 'tax.forms.view',                  'module' => 'tax', 'display' => 'View BIR forms'],
        ['name' => 'tax.forms.generate',              'module' => 'tax', 'display' => 'Generate BIR forms'],
        ['name' => 'tax.forms.file',                  'module' => 'tax', 'display' => 'File BIR forms (eBIRForms/EFPS)'],
        ['name' => 'tax.eis.transmit',                'module' => 'tax', 'display' => 'Transmit invoices to EIS'],
        ['name' => 'tax.form_2307_received.view',     'module' => 'tax', 'display' => 'View received 2307 certificates'],
        ['name' => 'tax.form_2307_received.write',    'module' => 'tax', 'display' => 'Record / amend received 2307 certificates'],
        ['name' => 'tax.form_2307_received.reject',   'module' => 'tax', 'display' => 'Reject received 2307 certificates'],
        ['name' => 'tax.osd_election.view',           'module' => 'tax', 'display' => 'View OSD / deduction-regime elections'],
        ['name' => 'tax.osd_election.supersede',      'module' => 'tax', 'display' => 'File BIR-approved amendment to an OSD election (MFA)'],

        // Payroll
        ['name' => 'payroll.runs.view',     'module' => 'payroll', 'display' => 'View payroll runs'],
        ['name' => 'payroll.runs.compute',  'module' => 'payroll', 'display' => 'Compute payroll'],
        ['name' => 'payroll.runs.approve',  'module' => 'payroll', 'display' => 'Approve payroll runs'],
        ['name' => 'payroll.statutory.file','module' => 'payroll', 'display' => 'File statutory remittances'],

        // HR
        ['name' => 'hr.employees.view',   'module' => 'hr', 'display' => 'View employees'],
        ['name' => 'hr.employees.manage', 'module' => 'hr', 'display' => 'Manage employees (including PII)'],
        ['name' => 'hr.leave.approve',    'module' => 'hr', 'display' => 'Approve leave requests'],

        // Fixed Assets
        ['name' => 'assets.view',              'module' => 'assets', 'display' => 'View fixed asset register'],
        ['name' => 'assets.register',          'module' => 'assets', 'display' => 'Register new fixed assets'],
        ['name' => 'assets.depreciation.run',  'module' => 'assets', 'display' => 'Run monthly depreciation (posts JV)'],
        ['name' => 'assets.dispose',           'module' => 'assets', 'display' => 'Dispose fixed assets (posts gain/loss JV)'],

        // Projects
        ['name' => 'projects.view',    'module' => 'projects', 'display' => 'View projects'],
        ['name' => 'projects.create',  'module' => 'projects', 'display' => 'Create / update projects'],
        ['name' => 'projects.wip',     'module' => 'projects', 'display' => 'Recognize WIP revenue (posts JV)'],
        ['name' => 'projects.close',   'module' => 'projects', 'display' => 'Close / cancel projects'],

        // Manufacturing
        ['name' => 'manufacturing.view',        'module' => 'manufacturing', 'display' => 'View BOMs and work orders'],
        ['name' => 'manufacturing.bom.manage',  'module' => 'manufacturing', 'display' => 'Create / edit Bills of Materials'],
        ['name' => 'manufacturing.wo.manage',   'module' => 'manufacturing', 'display' => 'Create and start work orders'],
        ['name' => 'manufacturing.wo.complete', 'module' => 'manufacturing', 'display' => 'Complete production runs (posts inventory JV)'],

        // Audit
        ['name' => 'audit.events.view',  'module' => 'audit', 'display' => 'View audit events'],
        ['name' => 'audit.chain.verify', 'module' => 'audit', 'display' => 'Verify audit chain'],

        // Reporting
        ['name' => 'reporting.financials.view', 'module' => 'reporting', 'display' => 'View financial statements'],
    ];

    /** @var array<string, array<int, string>> */
    private const ROLE_PERMISSIONS = [
        'Admin' => ['*'],   // wildcard = all permissions

        'Accountant' => [
            'accounting.journals.view',    'accounting.journals.create', 'accounting.coa.manage',
            'sales.invoices.view',         'sales.invoices.create',
            'procurement.bills.view',      'procurement.bills.create',
            'tax.forms.view',              'tax.forms.generate',
            'reporting.financials.view',   'audit.events.view',
            'identity.users.view',
            'assets.view',                 'assets.register',
            'projects.view',               'projects.create',
            'manufacturing.view',          'manufacturing.bom.manage',   'manufacturing.wo.manage',
        ],

        'Approver' => [
            'accounting.journals.view',    'accounting.journals.post',   'accounting.journals.reverse',
            'accounting.periods.lock',
            'sales.invoices.view',         'sales.invoices.void',
            'procurement.bills.view',      'procurement.bills.post',
            'tax.forms.view',              'tax.forms.file',             'tax.eis.transmit',
            'payroll.runs.view',           'payroll.runs.approve',       'payroll.statutory.file',
            'reporting.financials.view',   'audit.events.view',          'audit.chain.verify',
            'assets.view',                 'assets.depreciation.run',    'assets.dispose',
            'projects.view',               'projects.wip',               'projects.close',
            'manufacturing.view',          'manufacturing.wo.complete',
        ],

        'Auditor' => [
            'accounting.journals.view',    'sales.invoices.view',
            'procurement.bills.view',      'tax.forms.view',
            'payroll.runs.view',           'reporting.financials.view',
            'audit.events.view',           'audit.chain.verify',
            'hr.employees.view',           'identity.users.view',
            'assets.view',                 'projects.view',              'manufacturing.view',
        ],

        'Cashier' => [
            'sales.invoices.create', 'sales.or.issue', 'sales.pos.transact',
        ],

        'HR' => [
            'hr.employees.view', 'hr.employees.manage', 'hr.leave.approve',
        ],

        'Payroll' => [
            'payroll.runs.view', 'payroll.runs.compute',
            'hr.employees.view', 'reporting.financials.view',
        ],

        'Viewer' => [
            'accounting.journals.view', 'sales.invoices.view',
            'procurement.bills.view',   'tax.forms.view',
            'payroll.runs.view',        'reporting.financials.view',
            'assets.view',              'projects.view',              'manufacturing.view',
        ],
    ];

    public function run(): void
    {
        // Reset cached roles/permissions
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Permissions
        foreach (self::PERMISSIONS as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'sanctum'],
                [
                    'id'           => Uuid::uuid4()->toString(),
                    'module'       => $perm['module'],
                    'display_name' => $perm['display'],
                ],
            );
        }

        // Roles
        foreach (self::ROLES as $name => $meta) {
            $role = Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'sanctum'],
                [
                    'id'           => Uuid::uuid4()->toString(),
                    'display_name' => $meta['display'],
                    'description'  => $meta['description'],
                    'is_system'    => true,
                ],
            );

            $permissions = self::ROLE_PERMISSIONS[$name] ?? [];

            $assigned = $permissions === ['*']
                ? Permission::all()
                : Permission::whereIn('name', $permissions)->get();

            $role->syncPermissions($assigned);
        }

        $this->command?->info(sprintf(
            'Seeded %d roles and %d permissions.',
            count(self::ROLES),
            count(self::PERMISSIONS),
        ));
    }
}
