import { useState } from 'react';

import { DataTable, type Column } from '@/shared/components/DataTable';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

import { useEmployees, type Employee } from '../api/employees';

export function EmployeesPage() {
    const user = useAuthStore((s) => s.user);
    const canManage = hasPermission(user, 'hr.employees.manage');

    const [search, setSearch] = useState('');
    const [activeOnly, setActiveOnly] = useState(true);

    const { data: employees, isLoading } = useEmployees({
        search: search || undefined,
        active_only: activeOnly,
    });

    const columns: Column<Employee>[] = [
        {
            key: 'employee_no',
            header: 'Employee #',
            render: (e) => (
                <a
                    href={`#/hr/employees/${e.id}/edit`}
                    className="font-mono text-primary hover:underline"
                >
                    {e.employee_no}
                </a>
            ),
        },
        { key: 'full_name', header: 'Full Name' },
        {
            key: 'employment_status',
            header: 'Employment',
            render: (e) => <EmploymentBadge status={e.employment_status} />,
        },
        {
            key: 'hired_on',
            header: 'Hired On',
            numeric: true,
            render: (e) => e.hired_on ?? '—',
        },
        {
            key: 'email',
            header: 'Email',
            render: (e) => e.email ?? <span className="text-muted-foreground">—</span>,
        },
        {
            key: 'status',
            header: 'Status',
            render: (e) =>
                e.is_active ? (
                    <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">
                        Active
                    </span>
                ) : (
                    <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                        Separated
                    </span>
                ),
        },
    ];

    return (
        <div className="container py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">Employees</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        HR master list. PII (TIN, SSS, PhilHealth, Pag-IBIG) is masked for
                        non-HR roles. Payroll draws compensation data from here.
                    </p>
                </div>

                <div className="flex items-center gap-3 text-sm">
                    <input
                        type="search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search name / employee #"
                        className="rounded-md border bg-background px-2 py-1"
                    />
                    <label className="flex items-center gap-1">
                        <input
                            type="checkbox"
                            checked={activeOnly}
                            onChange={(e) => setActiveOnly(e.target.checked)}
                        />
                        Active only
                    </label>
                    {canManage && (
                        <a
                            href="#/hr/employees/new"
                            className="rounded-md bg-primary px-3 py-1.5 font-medium text-primary-foreground hover:opacity-90"
                        >
                            + New Employee
                        </a>
                    )}
                </div>
            </header>

            {employees && employees.length > 0 && (
                <div className="mt-4 inline-block rounded-md border bg-card px-4 py-2 text-sm">
                    <span className="text-xs uppercase tracking-wide text-muted-foreground">
                        Total headcount
                    </span>
                    <span className="ml-3 font-semibold tabular-nums">{employees.length}</span>
                    <span className="ml-1 text-xs text-muted-foreground">
                        {activeOnly ? 'active' : 'all'} employee{employees.length === 1 ? '' : 's'}
                    </span>
                </div>
            )}

            <div className="mt-6">
                <DataTable
                    columns={columns}
                    rows={employees}
                    rowKey={(e) => e.id}
                    isLoading={isLoading}
                    emptyState="No employees match the current filters."
                />
            </div>
        </div>
    );
}

function EmploymentBadge({ status }: { status: Employee['employment_status'] }) {
    const styles: Record<Employee['employment_status'], string> = {
        regular:       'bg-emerald-100 text-emerald-800',
        probationary:  'bg-amber-100 text-amber-800',
        contract:      'bg-blue-100 text-blue-800',
        project:       'bg-purple-100 text-purple-800',
        consultant:    'bg-slate-100 text-slate-700',
    };
    return (
        <span className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${styles[status]}`}>
            {status}
        </span>
    );
}
