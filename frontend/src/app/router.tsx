import { useEffect, useState } from 'react';

import { DashboardPage } from '@/modules/dashboard/pages/DashboardPage';
import { useBootstrapAuth, useLogout } from '@/modules/identity/api/auth';
import { LoginPage } from '@/modules/identity/pages/LoginPage';
import { FiscalPeriodsPage } from '@/modules/accounting/pages/FiscalPeriodsPage';
import { JournalEntriesPage } from '@/modules/accounting/pages/JournalEntriesPage';
import { JournalEntryEditor } from '@/modules/accounting/pages/JournalEntryEditor';
import { FixedAssetsPage } from '@/modules/fixed-assets/pages/FixedAssetsPage';
import { RegisterAssetPage } from '@/modules/fixed-assets/pages/RegisterAssetPage';
import { EmployeesPage } from '@/modules/hr/pages/EmployeesPage';
import { EmployeeEditorPage } from '@/modules/hr/pages/EmployeeEditorPage';
import { LeaveRequestsPage } from '@/modules/hr/pages/LeaveRequestsPage';
import { SubmitLeaveRequestPage } from '@/modules/hr/pages/SubmitLeaveRequestPage';
import { CompensationPackagePage } from '@/modules/hr/pages/CompensationPackagePage';
import { DepartmentsPage } from '@/modules/hr/pages/DepartmentsPage';
import { PositionsPage } from '@/modules/hr/pages/PositionsPage';
import { BillOfMaterialsPage } from '@/modules/manufacturing/pages/BillOfMaterialsPage';
import { BomDetailPage } from '@/modules/manufacturing/pages/BomDetailPage';
import { WorkOrdersPage } from '@/modules/manufacturing/pages/WorkOrdersPage';
import { WorkOrderDetailPage } from '@/modules/manufacturing/pages/WorkOrderDetailPage';
import { ProjectsPage } from '@/modules/projects/pages/ProjectsPage';
import { ProjectEditorPage } from '@/modules/projects/pages/ProjectEditorPage';
import { ProjectDetailPage } from '@/modules/projects/pages/ProjectDetailPage';
import { InventoryListPage } from '@/modules/inventory/pages/InventoryListPage';
import { ItemsPage } from '@/modules/inventory/pages/ItemsPage';
import { StockMovementsPage } from '@/modules/inventory/pages/StockMovementsPage';
import { FinalPayPage } from '@/modules/payroll/pages/FinalPayPage';
import { LoanDeductionsPage } from '@/modules/payroll/pages/LoanDeductionsPage';
import { PayrollFormsPage } from '@/modules/payroll/pages/PayrollFormsPage';
import { PayrollRunDetailPage } from '@/modules/payroll/pages/PayrollRunDetailPage';
import { PayrollRunsPage } from '@/modules/payroll/pages/PayrollRunsPage';
import { PostVendorBillPage } from '@/modules/procurement/pages/PostVendorBillPage';
import { PurchaseOrdersPage } from '@/modules/procurement/pages/PurchaseOrdersPage';
import { VendorBillsPage } from '@/modules/procurement/pages/VendorBillsPage';
import { VendorEditorPage } from '@/modules/procurement/pages/VendorEditorPage';
import { VendorsPage } from '@/modules/procurement/pages/VendorsPage';
import { CustomerDetailPage } from '@/modules/sales/pages/CustomerDetailPage';
import { CustomerEditorPage } from '@/modules/sales/pages/CustomerEditorPage';
import { CustomersPage } from '@/modules/sales/pages/CustomersPage';
import { IssueSalesInvoicePage } from '@/modules/sales/pages/IssueSalesInvoicePage';
import { OfficialReceiptsPage } from '@/modules/sales/pages/OfficialReceiptsPage';
import { PosTerminalPage } from '@/modules/sales/pages/PosTerminalPage';
import { SalesInvoiceDetailPage } from '@/modules/sales/pages/SalesInvoiceDetailPage';
import { SalesInvoicesPage } from '@/modules/sales/pages/SalesInvoicesPage';
import { Form2307ReceivedPage } from '@/modules/tax/pages/Form2307ReceivedPage';
import { ItrWizardPage } from '@/modules/tax/pages/ItrWizardPage';
import { OsdElectionsPage } from '@/modules/tax/pages/OsdElectionsPage';
import { TaxFormsPage } from '@/modules/tax/pages/TaxFormsPage';
import { ReportingDashboardPage } from '@/modules/reporting/pages/ReportingDashboardPage';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

/**
 * Hash-based in-memory router. Keeps URLs bookmarkable without depending on
 * a separate routing library while the app shell is still small. Will graduate
 * to @tanstack/react-router (file-based routes) once the page count justifies it.
 */
type Route =
    | { name: 'dashboard' }
    | { name: 'accounting.journals' }
    | { name: 'accounting.journals.new' }
    | { name: 'accounting.journals.edit'; id: string }
    | { name: 'accounting.fiscal_periods' }
    | { name: 'fixed_assets.list' }
    | { name: 'fixed_assets.register' }
    | { name: 'hr.employees' }
    | { name: 'hr.leave_requests' }
    | { name: 'hr.leave_requests.new' }
    | { name: 'manufacturing.boms' }
    | { name: 'manufacturing.boms.detail'; id: string }
    | { name: 'manufacturing.work_orders' }
    | { name: 'manufacturing.work_orders.detail'; id: string }
    | { name: 'projects.list' }
    | { name: 'projects.new' }
    | { name: 'projects.detail'; id: string }
    | { name: 'projects.edit'; id: string }
    | { name: 'hr.employees.new' }
    | { name: 'hr.employees.edit'; id: string }
    | { name: 'hr.employees.compensation'; id: string }
    | { name: 'hr.positions' }
    | { name: 'hr.departments' }
    | { name: 'inventory.items' }
    | { name: 'inventory.stock_movements' }
    | { name: 'inventory.list' }
    | { name: 'sales.invoices' }
    | { name: 'sales.invoices.new' }
    | { name: 'sales.invoices.detail'; id: string }
    | { name: 'sales.official_receipts' }
    | { name: 'sales.customers' }
    | { name: 'sales.customers.new' }
    | { name: 'sales.customers.detail'; id: string }
    | { name: 'sales.customers.edit'; id: string }
    | { name: 'sales.pos' }
    | { name: 'procurement.bills' }
    | { name: 'procurement.bills.new' }
    | { name: 'procurement.vendors' }
    | { name: 'procurement.vendors.new' }
    | { name: 'procurement.vendors.edit'; id: string }
    | { name: 'procurement.purchase_orders' }
    | { name: 'payroll.runs' }
    | { name: 'payroll.runs.detail'; id: string }
    | { name: 'payroll.forms' }
    | { name: 'payroll.final_pay' }
    | { name: 'payroll.loans' }
    | { name: 'tax.forms' }
    | { name: 'tax.itr_wizard' }
    | { name: 'tax.osd_elections' }
    | { name: 'tax.form_2307_received' }
    | { name: 'reporting' };

function parseHash(): Route {
    const h = window.location.hash.replace(/^#\/?/, '');
    if (h === 'accounting/journals/new') return { name: 'accounting.journals.new' };
    const journalEditMatch = h.match(/^accounting\/journals\/([^/]+)$/);
    if (journalEditMatch && journalEditMatch[1]) return { name: 'accounting.journals.edit', id: journalEditMatch[1] };
    if (h.startsWith('accounting/journals'))       return { name: 'accounting.journals' };
    if (h.startsWith('accounting/fiscal-periods')) return { name: 'accounting.fiscal_periods' };

    if (h.startsWith('fixed-assets/register'))     return { name: 'fixed_assets.register' };
    if (h.startsWith('fixed-assets'))              return { name: 'fixed_assets.list' };

    const bomDetailMatch = h.match(/^manufacturing\/boms\/([^/]+)$/);
    if (bomDetailMatch && bomDetailMatch[1])         return { name: 'manufacturing.boms.detail', id: bomDetailMatch[1] };
    if (h.startsWith('manufacturing/boms'))          return { name: 'manufacturing.boms' };
    const woDetailMatch = h.match(/^manufacturing\/work-orders\/([^/]+)$/);
    if (woDetailMatch && woDetailMatch[1])           return { name: 'manufacturing.work_orders.detail', id: woDetailMatch[1] };
    if (h.startsWith('manufacturing/work-orders'))   return { name: 'manufacturing.work_orders' };
    if (h.startsWith('manufacturing'))               return { name: 'manufacturing.boms' };

    if (h === 'projects/new')                       return { name: 'projects.new' };
    const projectDetailMatch = h.match(/^projects\/([^/]+)\/detail$/);
    if (projectDetailMatch && projectDetailMatch[1]) return { name: 'projects.detail', id: projectDetailMatch[1] };
    const projectEditMatch = h.match(/^projects\/([^/]+)\/edit$/);
    if (projectEditMatch && projectEditMatch[1])    return { name: 'projects.edit', id: projectEditMatch[1] };
    if (h.startsWith('projects'))                   return { name: 'projects.list' };

    if (h === 'hr/employees/new')                  return { name: 'hr.employees.new' };
    const employeeEditMatch = h.match(/^hr\/employees\/([^/]+)\/edit$/);
    if (employeeEditMatch && employeeEditMatch[1]) return { name: 'hr.employees.edit', id: employeeEditMatch[1] };
    const employeeCompMatch = h.match(/^hr\/employees\/([^/]+)\/compensation$/);
    if (employeeCompMatch && employeeCompMatch[1]) return { name: 'hr.employees.compensation', id: employeeCompMatch[1] };
    if (h.startsWith('hr/employees'))              return { name: 'hr.employees' };
    if (h.startsWith('hr/positions'))             return { name: 'hr.positions' };
    if (h.startsWith('hr/departments'))           return { name: 'hr.departments' };
    if (h === 'hr/leave-requests/new')             return { name: 'hr.leave_requests.new' };
    if (h.startsWith('hr/leave-requests'))         return { name: 'hr.leave_requests' };

    if (h.startsWith('inventory/stock-movements')) return { name: 'inventory.stock_movements' };
    if (h.startsWith('inventory/list'))            return { name: 'inventory.list' };
    if (h.startsWith('inventory/items'))           return { name: 'inventory.items' };

    if (h === 'sales/invoices/new')                return { name: 'sales.invoices.new' };
    const invoiceMatch = h.match(/^sales\/invoices\/([^/]+)$/);
    if (invoiceMatch && invoiceMatch[1]) return { name: 'sales.invoices.detail', id: invoiceMatch[1] };
    if (h.startsWith('sales/invoices'))            return { name: 'sales.invoices' };
    if (h.startsWith('sales/official-receipts'))   return { name: 'sales.official_receipts' };

    if (h === 'sales/customers/new')               return { name: 'sales.customers.new' };
    const customerDetailMatch = h.match(/^sales\/customers\/([^/]+)\/detail$/);
    if (customerDetailMatch && customerDetailMatch[1]) return { name: 'sales.customers.detail', id: customerDetailMatch[1] };
    const customerEditMatch = h.match(/^sales\/customers\/([^/]+)\/edit$/) ?? h.match(/^sales\/customers\/([^/]+)$/);
    if (customerEditMatch && customerEditMatch[1]) return { name: 'sales.customers.edit', id: customerEditMatch[1] };
    if (h.startsWith('sales/customers'))           return { name: 'sales.customers' };
    if (h.startsWith('sales/pos'))                 return { name: 'sales.pos' };

    if (h === 'procurement/bills/new')             return { name: 'procurement.bills.new' };
    if (h.startsWith('procurement/bills'))         return { name: 'procurement.bills' };
    if (h === 'procurement/vendors/new')           return { name: 'procurement.vendors.new' };
    const vendorEditMatch = h.match(/^procurement\/vendors\/([^/]+)\/edit$/);
    if (vendorEditMatch && vendorEditMatch[1]) return { name: 'procurement.vendors.edit', id: vendorEditMatch[1] };
    if (h.startsWith('procurement/vendors'))       return { name: 'procurement.vendors' };
    if (h.startsWith('procurement/purchase-orders')) return { name: 'procurement.purchase_orders' };

    const payrollDetailMatch = h.match(/^payroll\/runs\/([^/]+)$/);
    if (payrollDetailMatch && payrollDetailMatch[1]) return { name: 'payroll.runs.detail', id: payrollDetailMatch[1] };
    if (h.startsWith('payroll/runs'))              return { name: 'payroll.runs' };
    if (h.startsWith('payroll/forms'))             return { name: 'payroll.forms' };
    if (h.startsWith('payroll/final-pay'))         return { name: 'payroll.final_pay' };
    if (h.startsWith('payroll/loans'))             return { name: 'payroll.loans' };

    if (h.startsWith('tax/forms'))                  return { name: 'tax.forms' };
    if (h.startsWith('tax/itr-wizard'))            return { name: 'tax.itr_wizard' };
    if (h.startsWith('tax/osd-elections'))         return { name: 'tax.osd_elections' };
    if (h.startsWith('tax/form-2307-received'))    return { name: 'tax.form_2307_received' };
    if (h.startsWith('reporting'))                 return { name: 'reporting' };
    return { name: 'dashboard' };
}

export function AppRouter() {
    const isAuthenticated = useAuthStore((s) => s.isAuthenticated);
    const { isBootstrapping } = useBootstrapAuth();
    const [route, setRoute] = useState<Route>(parseHash);

    useEffect(() => {
        const handler = () => setRoute(parseHash());
        window.addEventListener('hashchange', handler);
        return () => window.removeEventListener('hashchange', handler);
    }, []);

    if (isBootstrapping) {
        return (
            <div className="flex min-h-screen items-center justify-center">
                <div className="text-sm text-muted-foreground">Loading…</div>
            </div>
        );
    }

    if (!isAuthenticated) {
        return <LoginPage />;
    }

    return (
        <div>
            <TopNav />
            <RouteGuard route={route} />
        </div>
    );
}

/**
 * Gates each route on the permissions the backend FormRequest would have
 * checked. If the user lacks the permission for the requested route, render
 * a friendly "not authorized" view rather than letting the React page mount
 * and round-trip to the API for a 403.
 *
 * Permission keys mirror IdentityRolesAndPermissionsSeeder.
 */
function RouteGuard({ route }: { route: Route }) {
    const user = useAuthStore((s) => s.user);

    const required = ROUTE_PERMISSION_MAP[route.name];
    if (required && !hasPermission(user, required)) {
        return <NotAuthorized requiredPermission={required} />;
    }

    switch (route.name) {
        case 'dashboard':                       return <DashboardPage />;
        case 'accounting.journals':             return <JournalEntriesPage />;
        case 'accounting.journals.new':         return <JournalEntryEditor mode="new" />;
        case 'accounting.journals.edit':        return <JournalEntryEditor mode="edit" journalId={route.id} />;
        case 'accounting.fiscal_periods':       return <FiscalPeriodsPage />;
        case 'fixed_assets.list':               return <FixedAssetsPage />;
        case 'fixed_assets.register':           return <RegisterAssetPage />;
        case 'manufacturing.boms':              return <BillOfMaterialsPage />;
        case 'manufacturing.boms.detail':       return <BomDetailPage bomId={route.id} />;
        case 'manufacturing.work_orders':       return <WorkOrdersPage />;
        case 'manufacturing.work_orders.detail': return <WorkOrderDetailPage workOrderId={route.id} />;
        case 'projects.list':                   return <ProjectsPage />;
        case 'projects.new':                    return <ProjectEditorPage />;
        case 'projects.detail':                 return <ProjectDetailPage projectId={route.id} />;
        case 'projects.edit':                   return <ProjectEditorPage projectId={route.id} />;
        case 'hr.employees':                    return <EmployeesPage />;
        case 'hr.employees.new':                return <EmployeeEditorPage mode="new" />;
        case 'hr.employees.edit':               return <EmployeeEditorPage mode="edit" employeeId={route.id} />;
        case 'hr.employees.compensation':       return <CompensationPackagePage employeeId={route.id} />;
        case 'hr.positions':                    return <PositionsPage />;
        case 'hr.departments':                  return <DepartmentsPage />;
        case 'hr.leave_requests':               return <LeaveRequestsPage />;
        case 'hr.leave_requests.new':           return <SubmitLeaveRequestPage />;
        case 'inventory.items':                 return <ItemsPage />;
        case 'inventory.stock_movements':       return <StockMovementsPage />;
        case 'inventory.list':                  return <InventoryListPage />;
        case 'sales.invoices':                  return <SalesInvoicesPage />;
        case 'sales.invoices.new':              return <IssueSalesInvoicePage />;
        case 'sales.invoices.detail':           return <SalesInvoiceDetailPage invoiceId={route.id} />;
        case 'sales.official_receipts':         return <OfficialReceiptsPage />;
        case 'sales.customers':                 return <CustomersPage />;
        case 'sales.customers.new':             return <CustomerEditorPage mode="new" />;
        case 'sales.customers.detail':          return <CustomerDetailPage customerId={route.id} />;
        case 'sales.customers.edit':            return <CustomerEditorPage mode="edit" customerId={route.id} />;
        case 'sales.pos':                       return <PosTerminalPage />;
        case 'procurement.bills':               return <VendorBillsPage />;
        case 'procurement.bills.new':           return <PostVendorBillPage />;
        case 'procurement.vendors':             return <VendorsPage />;
        case 'procurement.vendors.new':         return <VendorEditorPage mode="new" />;
        case 'procurement.vendors.edit':        return <VendorEditorPage mode="edit" vendorId={route.id} />;
        case 'procurement.purchase_orders':     return <PurchaseOrdersPage />;
        case 'payroll.runs':                    return <PayrollRunsPage />;
        case 'payroll.runs.detail':             return <PayrollRunDetailPage runId={route.id} />;
        case 'payroll.forms':                   return <PayrollFormsPage />;
        case 'payroll.final_pay':               return <FinalPayPage />;
        case 'payroll.loans':                   return <LoanDeductionsPage />;
        case 'tax.forms':                       return <TaxFormsPage />;
        case 'tax.itr_wizard':                  return <ItrWizardPage />;
        case 'tax.osd_elections':               return <OsdElectionsPage />;
        case 'tax.form_2307_received':          return <Form2307ReceivedPage />;
        case 'reporting':                       return <ReportingDashboardPage />;
        default:                                return null;
    }
}

const ROUTE_PERMISSION_MAP: Partial<Record<Route['name'], string>> = {
    'accounting.journals':            'accounting.journals.view',
    'accounting.journals.new':        'accounting.journals.create',
    'accounting.journals.edit':       'accounting.journals.create',
    'accounting.fiscal_periods':      'accounting.periods.lock',
    'fixed_assets.list':              'assets.view',
    'fixed_assets.register':          'assets.register',
    'manufacturing.boms':              'manufacturing.view',
    'manufacturing.boms.detail':      'manufacturing.view',
    'manufacturing.work_orders':      'manufacturing.view',
    'manufacturing.work_orders.detail': 'manufacturing.view',
    'projects.list':                  'projects.view',
    'projects.new':                   'projects.create',
    'projects.detail':                'projects.view',
    'projects.edit':                  'projects.create',
    'hr.employees':                   'hr.employees.view',
    'hr.employees.new':               'hr.employees.manage',
    'hr.employees.edit':              'hr.employees.view',
    'hr.employees.compensation':      'payroll.runs.compute',
    'hr.positions':                   'hr.employees.manage',
    'hr.departments':                 'hr.employees.manage',
    'hr.leave_requests':              'hr.employees.view',
    'hr.leave_requests.new':          'hr.employees.view',
    'inventory.items':                'inventory.items.view',
    'inventory.stock_movements':      'inventory.movements.view',
    'inventory.list':                 'inventory.list.generate',
    'sales.invoices':                 'sales.invoices.view',
    'sales.invoices.new':             'sales.invoices.create',
    'sales.invoices.detail':          'sales.invoices.view',
    'sales.official_receipts':        'sales.invoices.view',
    'sales.customers':                'sales.invoices.view',
    'sales.customers.new':            'sales.invoices.create',
    'sales.customers.detail':         'sales.invoices.view',
    'sales.customers.edit':           'sales.invoices.create',
    'sales.pos':                      'sales.pos.transact',
    'procurement.bills':              'procurement.bills.view',
    'procurement.bills.new':          'procurement.bills.post',
    'procurement.vendors':            'procurement.bills.view',
    'procurement.vendors.new':        'procurement.bills.create',
    'procurement.vendors.edit':       'procurement.bills.view',
    'procurement.purchase_orders':    'procurement.bills.view',
    'payroll.runs':                   'payroll.runs.view',
    'payroll.runs.detail':            'payroll.runs.view',
    'payroll.forms':                  'payroll.statutory.file',
    'payroll.final_pay':              'payroll.runs.compute',
    'payroll.loans':                  'payroll.runs.compute',
    'tax.forms':                      'tax.forms.view',
    'tax.itr_wizard':                 'tax.forms.generate',
    'tax.osd_elections':              'tax.osd_election.view',
    'tax.form_2307_received':         'tax.form_2307_received.view',
    'reporting':                      'accounting.journals.view',
};

function NotAuthorized({ requiredPermission }: { requiredPermission: string }) {
    return (
        <main className="container py-16">
            <div className="mx-auto max-w-md rounded-lg border bg-card p-6 text-center">
                <h1 className="text-lg font-semibold">Not authorized</h1>
                <p className="mt-2 text-sm text-muted-foreground">
                    Your account doesn't have the{' '}
                    <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">
                        {requiredPermission}
                    </code>{' '}
                    permission needed to view this page. Ask an administrator to grant it.
                </p>
                <a
                    href="#/"
                    className="mt-4 inline-block rounded-md border px-3 py-1.5 text-sm hover:bg-accent"
                >
                    Back to dashboard
                </a>
            </div>
        </main>
    );
}

function TopNav() {
    const user = useAuthStore((s) => s.user);
    const logout = useLogout();

    // Nav items hidden when the user lacks the matching permission. Each link's
    // permission mirrors the page's RouteGuard so the nav and the page agree.
    const links: Array<{ href: string; label: string; permission?: string }> = [
        { href: '#/',                                  label: 'Dashboard' },
        { href: '#/accounting/journals',               label: 'Journals',       permission: 'accounting.journals.view' },
        { href: '#/accounting/fiscal-periods',         label: 'Periods',        permission: 'accounting.periods.lock' },
        { href: '#/fixed-assets',                      label: 'Fixed Assets',   permission: 'assets.view' },
        { href: '#/manufacturing/boms',                label: 'BOMs',           permission: 'manufacturing.view' },
        { href: '#/manufacturing/work-orders',         label: 'Work Orders',    permission: 'manufacturing.view' },
        { href: '#/projects',                          label: 'Projects',       permission: 'projects.view' },
        { href: '#/hr/employees',                      label: 'Employees',      permission: 'hr.employees.view' },
        { href: '#/hr/departments',                    label: 'Departments',    permission: 'hr.employees.manage' },
        { href: '#/hr/positions',                      label: 'Positions',      permission: 'hr.employees.manage' },
        { href: '#/hr/leave-requests',                 label: 'Leave',          permission: 'hr.employees.view' },
        { href: '#/inventory/items',                   label: 'Inventory',      permission: 'inventory.items.view' },
        { href: '#/sales/customers',                   label: 'Customers',      permission: 'sales.invoices.view' },
        { href: '#/sales/invoices',                    label: 'Invoices',       permission: 'sales.invoices.view' },
        { href: '#/sales/official-receipts',           label: 'ORs',            permission: 'sales.invoices.view' },
        { href: '#/sales/pos',                         label: 'POS',            permission: 'sales.pos.transact' },
        { href: '#/procurement/vendors',               label: 'Vendors',        permission: 'procurement.bills.view' },
        { href: '#/procurement/purchase-orders',       label: 'POs',            permission: 'procurement.bills.view' },
        { href: '#/procurement/bills',                 label: 'Vendor Bills',   permission: 'procurement.bills.view' },
        { href: '#/payroll/runs',                      label: 'Payroll',        permission: 'payroll.runs.view' },
        { href: '#/payroll/forms',                     label: 'Payroll Forms',  permission: 'payroll.statutory.file' },
        { href: '#/payroll/final-pay',                 label: 'Final Pay',      permission: 'payroll.runs.compute' },
        { href: '#/payroll/loans',                     label: 'Loans',          permission: 'payroll.runs.compute' },
        { href: '#/tax/forms',                         label: 'BIR Forms',      permission: 'tax.forms.view' },
        { href: '#/tax/itr-wizard',                    label: 'ITR Wizard',     permission: 'tax.forms.generate' },
        { href: '#/tax/form-2307-received',            label: '2307 Received',  permission: 'tax.form_2307_received.view' },
        { href: '#/tax/osd-elections',                 label: 'OSD Elections',  permission: 'tax.osd_election.view' },
        { href: '#/reporting',                         label: 'Reports',        permission: 'accounting.journals.view' },
    ];

    return (
        <header className="border-b">
            <div className="container flex h-14 items-center justify-between">
                <nav className="flex items-center gap-4 text-sm">
                    <a href="#/" className="font-semibold">PHA</a>
                    {links
                        .filter((l) => !l.permission || hasPermission(user, l.permission))
                        .map((l) => (
                            <NavLink key={l.href} href={l.href} label={l.label} />
                        ))}
                </nav>
                <div className="flex items-center gap-4 text-sm">
                    <span className="text-muted-foreground">
                        {user?.fullName} · {user?.email}
                    </span>
                    <button
                        type="button"
                        onClick={() => logout.mutate()}
                        disabled={logout.isPending}
                        className="rounded-md border px-3 py-1 hover:bg-accent disabled:opacity-50"
                    >
                        Sign out
                    </button>
                </div>
            </div>
        </header>
    );
}

function NavLink({ href, label }: { href: string; label: string }) {
    return (
        <a href={href} className="text-muted-foreground hover:text-foreground">
            {label}
        </a>
    );
}
