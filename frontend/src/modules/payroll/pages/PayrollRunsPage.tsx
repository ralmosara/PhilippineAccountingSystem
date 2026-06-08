import { useState } from 'react';

import { DataTable, type Column } from '@/shared/components/DataTable';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';
import { formatPhp } from '@/shared/lib/money';

import {
    useApprovePayrollRun,
    useComputePayrollRun,
    usePayrollRuns,
    type PayrollRun,
} from '../api/payroll-runs';

export function PayrollRunsPage() {
    const user = useAuthStore((s) => s.user);
    const { data: runs, isLoading } = usePayrollRuns();
    const compute = useComputePayrollRun();
    const approve = useApprovePayrollRun();

    const canApprove = hasPermission(user, 'payroll.runs.approve');
    const canCompute = hasPermission(user, 'payroll.runs.compute');

    // Default new-run period to "this month" semi-monthly cutoff
    const today = new Date();
    const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().slice(0, 10);
    const endOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().slice(0, 10);
    const [from, setFrom] = useState(startOfMonth);
    const [to, setTo] = useState(endOfMonth);

    const columns: Column<PayrollRun>[] = [
        {
            key: 'period',
            header: 'Period',
            render: (r) => (
                <a
                    href={`#/payroll/runs/${r.id}`}
                    className="text-primary hover:underline"
                >
                    {r.period_start} – {r.period_end}
                </a>
            ),
        },
        { key: 'status', header: 'Status', render: (r) => <RunStatusBadge status={r.status} /> },
        {
            key: 'employees',
            header: 'Employees',
            align: 'right',
            numeric: true,
            render: (r) => r.payslip_lines.length,
        },
        { key: 'gross', header: 'Gross', align: 'right', numeric: true, render: (r) => formatPhp(r.total_gross) },
        { key: 'sss',   header: 'SSS',   align: 'right', numeric: true, render: (r) => formatPhp(r.total_sss) },
        { key: 'phic',  header: 'PHIC',  align: 'right', numeric: true, render: (r) => formatPhp(r.total_phic) },
        { key: 'hdmf',  header: 'HDMF',  align: 'right', numeric: true, render: (r) => formatPhp(r.total_hdmf) },
        { key: 'wht',   header: 'WHT',   align: 'right', numeric: true, render: (r) => formatPhp(r.total_wht) },
        { key: 'net',   header: 'Net',   align: 'right', numeric: true, render: (r) => formatPhp(r.total_net) },
        {
            key: 'actions',
            header: '',
            render: (r) =>
                r.status === 'computed' && canApprove ? (
                    <button
                        type="button"
                        onClick={() => approve.mutate(r.id)}
                        disabled={approve.isPending}
                        className="rounded-md border px-2 py-0.5 text-xs hover:bg-accent disabled:opacity-50"
                    >
                        Approve
                    </button>
                ) : null,
        },
    ];

    return (
        <div className="container py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">Payroll Runs</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        TRAIN-aligned withholding, SSS/PHIC/HDMF contributions. Approval (MFA-gated)
                        posts the JV and queues statutory remittances.
                    </p>
                </div>
            </header>

            {canCompute && (
                <div className="mt-4 flex flex-wrap items-end gap-3 rounded-lg border bg-card p-4">
                    <div>
                        <label className="block text-xs uppercase tracking-wide text-muted-foreground">
                            Period start
                        </label>
                        <input
                            type="date"
                            value={from}
                            onChange={(e) => setFrom(e.target.value)}
                            className="mt-1 rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </div>
                    <div>
                        <label className="block text-xs uppercase tracking-wide text-muted-foreground">
                            Period end
                        </label>
                        <input
                            type="date"
                            value={to}
                            onChange={(e) => setTo(e.target.value)}
                            className="mt-1 rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </div>
                    <button
                        type="button"
                        onClick={() => compute.mutate({ period_start: from, period_end: to })}
                        disabled={compute.isPending}
                        className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                    >
                        {compute.isPending ? 'Computing…' : 'Compute new run'}
                    </button>
                </div>
            )}

            {(compute.isError || approve.isError) && (
                <div className="mt-3 rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                    {((compute.error || approve.error) as { response?: { data?: { message?: string } } })
                        ?.response?.data?.message ?? 'Operation failed.'}
                </div>
            )}

            <div className="mt-6">
                <DataTable
                    columns={columns}
                    rows={runs}
                    rowKey={(r) => r.id}
                    isLoading={isLoading}
                    emptyState="No payroll runs yet. Compute one for the current period above."
                />
            </div>
        </div>
    );
}

function RunStatusBadge({ status }: { status: PayrollRun['status'] }) {
    const styles = {
        draft:    'bg-slate-100 text-slate-700',
        computed: 'bg-blue-100 text-blue-800',
        approved: 'bg-emerald-100 text-emerald-800',
        paid:     'bg-purple-100 text-purple-800',
    } as const;
    return (
        <span className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${styles[status]}`}>
            {status}
        </span>
    );
}
