import { useState } from 'react';

import { DataTable, type Column } from '@/shared/components/DataTable';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';
import { formatPhp } from '@/shared/lib/money';

import {
    useComputeFinalPayRun,
    type FinalPayRun,
    type PayrollRunLine,
    type SeparationReason,
} from '../api/payroll-runs';

const SEPARATION_REASONS: { value: SeparationReason; label: string }[] = [
    { value: 'resigned',     label: 'Resigned (voluntary)' },
    { value: 'terminated',   label: 'Terminated (without just cause)' },
    { value: 'redundancy',   label: 'Redundancy' },
    { value: 'retrenchment', label: 'Retrenchment' },
    { value: 'closure',      label: 'Business closure / cessation' },
    { value: 'illness',      label: 'Disease / illness (Art. 299)' },
    { value: 'other',        label: 'Other' },
];

export function FinalPayPage() {
    const user = useAuthStore((s) => s.user);
    const canCompute = hasPermission(user, 'payroll.runs.compute');

    const compute = useComputeFinalPayRun();

    const today = new Date().toISOString().slice(0, 10);
    const [employeeId,       setEmployeeId]       = useState('');
    const [separationDate,   setSeparationDate]   = useState(today);
    const [separationReason, setSeparationReason] = useState<SeparationReason>('resigned');

    const [result, setResult] = useState<FinalPayRun | null>(null);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        setResult(null);
        compute.mutate(
            { employee_id: employeeId, separation_date: separationDate, separation_reason: separationReason },
            { onSuccess: (run) => setResult(run) },
        );
    }

    const lineColumns: Column<PayrollRunLine>[] = [
        {
            key: 'line_no',
            header: '#',
            render: (l) => <span className="tabular-nums text-muted-foreground">{l.line_no}</span>,
        },
        {
            key: 'type',
            header: 'Type',
            render: (l) => <LineTypeBadge type={l.line_type} />,
        },
        { key: 'code',        header: 'Code',        render: (l) => <code className="text-xs">{l.code}</code> },
        { key: 'description', header: 'Description', render: (l) => l.description },
        {
            key: 'amount',
            header: 'Amount',
            align: 'right',
            numeric: true,
            render: (l) => (
                <span className={Number(l.amount) < 0 ? 'text-destructive' : ''}>
                    {formatPhp(l.amount)}
                </span>
            ),
        },
    ];

    // No permission — show informational gate
    if (!canCompute) {
        return (
            <div className="container py-8">
                <BackLink />
                <div className="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-6">
                    <h2 className="font-semibold text-amber-900">Permission required</h2>
                    <p className="mt-1 text-sm text-amber-800">
                        You need the <code className="rounded bg-amber-100 px-1">payroll.runs.compute</code> permission
                        to compute a final pay run. Contact your system administrator.
                    </p>
                </div>
            </div>
        );
    }

    return (
        <div className="container py-8">
            <BackLink />

            <header className="mt-4">
                <h1 className="text-2xl font-semibold">Final Pay Computation</h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    Per RA 7641 / Labor Code Art. 302 — last salary, pro-rated 13th month,
                    unused SIL, and separation pay (where applicable).
                </p>
            </header>

            {/* Input form */}
            <form
                onSubmit={handleSubmit}
                className="mt-6 rounded-lg border bg-card p-6"
            >
                <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                    Separation details
                </h2>

                <div className="grid gap-4 sm:grid-cols-3">
                    {/* Employee UUID */}
                    <div className="sm:col-span-3">
                        <label className="block text-xs uppercase tracking-wide text-muted-foreground">
                            Employee ID (UUID)
                        </label>
                        <input
                            type="text"
                            required
                            value={employeeId}
                            onChange={(e) => setEmployeeId(e.target.value.trim())}
                            placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                            className="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm font-mono"
                        />
                    </div>

                    {/* Separation date */}
                    <div>
                        <label className="block text-xs uppercase tracking-wide text-muted-foreground">
                            Separation date
                        </label>
                        <input
                            type="date"
                            required
                            value={separationDate}
                            onChange={(e) => setSeparationDate(e.target.value)}
                            className="mt-1 rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </div>

                    {/* Separation reason */}
                    <div className="sm:col-span-2">
                        <label className="block text-xs uppercase tracking-wide text-muted-foreground">
                            Separation reason
                        </label>
                        <select
                            required
                            value={separationReason}
                            onChange={(e) => setSeparationReason(e.target.value as SeparationReason)}
                            className="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm"
                        >
                            {SEPARATION_REASONS.map((r) => (
                                <option key={r.value} value={r.value}>
                                    {r.label}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <div className="mt-5 flex items-center gap-3">
                    <button
                        type="submit"
                        disabled={compute.isPending || !employeeId}
                        className="rounded-md bg-primary px-5 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                    >
                        {compute.isPending ? 'Computing…' : 'Compute final pay'}
                    </button>
                    {compute.isPending && (
                        <span className="text-sm text-muted-foreground">
                            Please wait — running BCMath computations…
                        </span>
                    )}
                </div>

                {compute.isError && (
                    <div className="mt-3 rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                        {(compute.error as { response?: { data?: { message?: string } } })
                            ?.response?.data?.message ?? 'Computation failed. Check the employee ID and try again.'}
                    </div>
                )}
            </form>

            {/* Result */}
            {result && <FinalPayResult run={result} lineColumns={lineColumns} />}
        </div>
    );
}

// ---------------------------------------------------------------------------

function FinalPayResult({
    run,
    lineColumns,
}: {
    run: FinalPayRun;
    lineColumns: Column<PayrollRunLine>[];
}) {
    const payslip  = run.payslips?.[0] ?? null;
    const totals   = run.totals;

    const gross  = totals?.gross   ?? payslip?.gross_compensation ?? '0';
    const sssEe  = totals?.sss_ee  ?? payslip?.sss_ee             ?? '0';
    const phicEe = totals?.phic_ee ?? payslip?.phic_ee            ?? '0';
    const hdmfEe = totals?.hdmf_ee ?? payslip?.hdmf_ee            ?? '0';
    const wht    = totals?.withholding_tax ?? payslip?.withholding_tax ?? '0';
    const netPay = totals?.net_pay ?? payslip?.net_pay             ?? '0';

    const lines: PayrollRunLine[] = payslip?.lines ?? [];

    return (
        <section className="mt-8">
            <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold">
                    Computed result&nbsp;
                    <span className="font-mono text-sm text-muted-foreground">#{run.run_no}</span>
                </h2>
                <a
                    href={`#/payroll/runs/${run.id}`}
                    className="rounded-md border px-3 py-1.5 text-sm hover:bg-accent"
                >
                    View payroll run →
                </a>
            </div>

            <p className="mt-1 text-sm text-muted-foreground">
                Status: <RunStatusBadge status={run.status} />
                &nbsp;· Run type: <code className="rounded bg-muted px-1 text-xs">{run.run_type}</code>
                {run.computed_at && <>&nbsp;· Computed {run.computed_at}</>}
            </p>

            {/* Summary tiles */}
            <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <Tile label="Gross pay"   value={formatPhp(gross)}  accent />
                <Tile label="SSS"         value={formatPhp(sssEe)} />
                <Tile label="PhilHealth"  value={formatPhp(phicEe)} />
                <Tile label="Pag-IBIG"    value={formatPhp(hdmfEe)} />
                <Tile label="WHT"         value={formatPhp(wht)} />
                <Tile label="Net pay"     value={formatPhp(netPay)} accent />
            </div>

            {/* Payslip lines */}
            {lines.length > 0 && (
                <div className="mt-6">
                    <h3 className="mb-2 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                        Payslip lines
                    </h3>
                    <DataTable
                        columns={lineColumns}
                        rows={lines}
                        rowKey={(l) => String(l.line_no)}
                        isLoading={false}
                        emptyState="No payslip lines."
                    />
                </div>
            )}
        </section>
    );
}

// ---------------------------------------------------------------------------
// Small reusable sub-components
// ---------------------------------------------------------------------------

function BackLink() {
    return (
        <a
            href="#/payroll/runs"
            className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            ← Payroll Runs
        </a>
    );
}

function Tile({ label, value, accent }: { label: string; value: string; accent?: boolean }) {
    return (
        <div
            className={`rounded-lg border p-3 ${
                accent ? 'border-primary/30 bg-primary/5' : 'bg-card'
            }`}
        >
            <div className="text-xs uppercase tracking-wide text-muted-foreground">{label}</div>
            <div className={`mt-1 tabular-nums ${accent ? 'text-base font-semibold' : 'text-sm'}`}>
                {value}
            </div>
        </div>
    );
}

function RunStatusBadge({ status }: { status: string }) {
    const styles: Record<string, string> = {
        draft:    'bg-slate-100 text-slate-700',
        computed: 'bg-blue-100 text-blue-800',
        approved: 'bg-emerald-100 text-emerald-800',
        paid:     'bg-purple-100 text-purple-800',
    };
    return (
        <span
            className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${
                styles[status] ?? 'bg-slate-100 text-slate-700'
            }`}
        >
            {status}
        </span>
    );
}

function LineTypeBadge({ type }: { type: string }) {
    const styles: Record<string, string> = {
        earning:   'bg-green-100 text-green-800',
        allowance: 'bg-sky-100 text-sky-800',
        deduction: 'bg-red-100 text-red-800',
        statutory: 'bg-orange-100 text-orange-800',
        tax:       'bg-yellow-100 text-yellow-800',
    };
    return (
        <span
            className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${
                styles[type] ?? 'bg-slate-100 text-slate-700'
            }`}
        >
            {type}
        </span>
    );
}
