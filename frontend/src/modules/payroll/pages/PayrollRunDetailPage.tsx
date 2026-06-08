import { DataTable, type Column } from '@/shared/components/DataTable';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';
import { formatPhp } from '@/shared/lib/money';

import {
    useApprovePayrollRun,
    usePayrollRun,
    type PayslipLine,
} from '../api/payroll-runs';

export function PayrollRunDetailPage({ runId }: { runId: string }) {
    const user = useAuthStore((s) => s.user);
    const { data: run, isLoading } = usePayrollRun(runId);
    const approve = useApprovePayrollRun();

    if (isLoading || !run) {
        return <div className="container py-8 text-sm text-muted-foreground">Loading…</div>;
    }

    const canApprove = hasPermission(user, 'payroll.runs.approve');

    const columns: Column<PayslipLine>[] = [
        {
            key: 'employee',
            header: 'Employee',
            render: (p) => p.employee_name ?? <span className="text-muted-foreground">{p.employee_id.slice(0, 8)}…</span>,
        },
        { key: 'gross',  header: 'Gross', align: 'right', numeric: true, render: (p) => formatPhp(p.gross) },
        { key: 'sss',    header: 'SSS',   align: 'right', numeric: true, render: (p) => `(${formatPhp(p.sss_ee)})` },
        { key: 'phic',   header: 'PHIC',  align: 'right', numeric: true, render: (p) => `(${formatPhp(p.phic_ee)})` },
        { key: 'hdmf',   header: 'HDMF',  align: 'right', numeric: true, render: (p) => `(${formatPhp(p.hdmf_ee)})` },
        { key: 'wht',    header: 'WHT',   align: 'right', numeric: true, render: (p) => `(${formatPhp(p.withholding_tax)})` },
        { key: 'net',    header: 'Net pay', align: 'right', numeric: true, render: (p) => <strong>{formatPhp(p.net_pay)}</strong> },
    ];

    return (
        <div className="container py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Payroll Run · {run.period_start} – {run.period_end}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {run.payslip_lines.length} employee{run.payslip_lines.length === 1 ? '' : 's'} ·{' '}
                        status {run.status}{run.approved_at && <> · approved {run.approved_at}</>}
                    </p>
                </div>
                <div className="flex gap-2 text-sm">
                    <a href="#/payroll/runs" className="rounded-md border px-3 py-1.5 hover:bg-accent">
                        ← Back
                    </a>
                    {run.status === 'computed' && canApprove && (
                        <button
                            type="button"
                            onClick={() => approve.mutate(run.id)}
                            disabled={approve.isPending}
                            className="rounded-md bg-primary px-3 py-1.5 font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                        >
                            {approve.isPending ? 'Approving…' : 'Approve & post JV'}
                        </button>
                    )}
                </div>
            </header>

            <div className="mt-6 grid grid-cols-2 gap-4 md:grid-cols-6">
                <Tile label="Total gross" value={formatPhp(run.total_gross)} />
                <Tile label="SSS"  value={formatPhp(run.total_sss)} />
                <Tile label="PHIC" value={formatPhp(run.total_phic)} />
                <Tile label="HDMF" value={formatPhp(run.total_hdmf)} />
                <Tile label="WHT"  value={formatPhp(run.total_wht)} />
                <Tile label="Net pay" value={formatPhp(run.total_net)} accent />
            </div>

            <div className="mt-6">
                <DataTable
                    columns={columns}
                    rows={run.payslip_lines}
                    rowKey={(p) => p.id}
                    isLoading={false}
                    emptyState="No employees in this run."
                />
            </div>
        </div>
    );
}

function Tile({ label, value, accent }: { label: string; value: string; accent?: boolean }) {
    return (
        <div className={`rounded-lg border p-3 ${accent ? 'border-primary/30 bg-primary/5' : 'bg-card'}`}>
            <div className="text-xs uppercase tracking-wide text-muted-foreground">{label}</div>
            <div className={`mt-1 tabular-nums ${accent ? 'text-lg font-semibold' : ''}`}>{value}</div>
        </div>
    );
}
