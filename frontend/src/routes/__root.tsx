import { createRootRoute, Link, Outlet } from '@tanstack/react-router';

import { useBootstrapAuth, useLogout } from '@/modules/identity/api/auth';
import { LoginPage } from '@/modules/identity/pages/LoginPage';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

export const Route = createRootRoute({
    component: RootLayout,
});

function RootLayout() {
    const isAuthenticated = useAuthStore((s) => s.isAuthenticated);
    const { isBootstrapping } = useBootstrapAuth();

    if (isBootstrapping) {
        return (
            <div className="flex min-h-screen items-center justify-center">
                <div className="text-sm text-muted-foreground">Loading…</div>
            </div>
        );
    }
    if (!isAuthenticated) return <LoginPage />;

    return (
        <div>
            <TopNav />
            <Outlet />
        </div>
    );
}

function TopNav() {
    const user = useAuthStore((s) => s.user);
    const logout = useLogout();

    const links: Array<{ to: string; label: string; permission?: string }> = [
        { to: '/',                          label: 'Dashboard' },
        { to: '/accounting/journals',       label: 'Journals',       permission: 'accounting.journals.view' },
        { to: '/accounting/fiscal-periods', label: 'Periods',        permission: 'accounting.periods.lock' },
        { to: '/inventory/items',           label: 'Inventory',      permission: 'inventory.items.view' },
        { to: '/sales/customers',           label: 'Customers',      permission: 'sales.invoices.view' },
        { to: '/sales/invoices',            label: 'Invoices',       permission: 'sales.invoices.view' },
        { to: '/sales/official-receipts',   label: 'ORs',            permission: 'sales.invoices.view' },
        { to: '/sales/pos',                 label: 'POS',            permission: 'sales.pos.transact' },
        { to: '/procurement/bills',         label: 'Vendor Bills',   permission: 'procurement.bills.view' },
        { to: '/payroll/runs',              label: 'Payroll',        permission: 'payroll.runs.view' },
        { to: '/payroll/forms',             label: 'Payroll Forms',  permission: 'payroll.statutory.file' },
        { to: '/tax/itr-wizard',            label: 'ITR Wizard',     permission: 'tax.forms.generate' },
        { to: '/tax/form-2307-received',    label: '2307 Received',  permission: 'tax.form_2307_received.view' },
        { to: '/tax/osd-elections',         label: 'OSD Elections',  permission: 'tax.osd_election.view' },
        { to: '/reporting',                 label: 'Reports',        permission: 'accounting.journals.view' },
    ];

    return (
        <header className="border-b">
            <div className="container flex h-14 items-center justify-between">
                <nav className="flex items-center gap-4 text-sm">
                    <Link to="/" className="font-semibold">PHA</Link>
                    {links
                        .filter((l) => !l.permission || hasPermission(user, l.permission))
                        .map((l) => (
                            <Link
                                key={l.to}
                                to={l.to}
                                className="text-muted-foreground hover:text-foreground"
                                activeProps={{ className: 'font-medium text-foreground' }}
                            >
                                {l.label}
                            </Link>
                        ))}
                </nav>
                <div className="flex items-center gap-4 text-sm">
                    <span className="text-muted-foreground">{user?.fullName} · {user?.email}</span>
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
