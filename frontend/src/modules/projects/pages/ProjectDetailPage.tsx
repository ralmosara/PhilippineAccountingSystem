import { useState } from 'react';

import { DataTable, type Column } from '@/shared/components/DataTable';
import { formatPhp } from '@/shared/lib/money';

import {
    useCloseProject,
    useLogTimesheet,
    useProject,
    useProjectTimesheets,
    useRecognizeWip,
    type TimesheetEntry,
} from '../api/projects';

interface Props {
    projectId: string;
}

export function ProjectDetailPage({ projectId }: Props) {
    const { data: project, isLoading } = useProject(projectId);
    const closeProject = useCloseProject(projectId);
    const [showCloseConfirm, setShowCloseConfirm] = useState(false);

    if (isLoading || !project) {
        return <div className="container py-8 text-sm text-muted-foreground">Loading…</div>;
    }

    return (
        <div className="container py-8 space-y-8">
            {/* Header card */}
            <div className="rounded-lg border bg-card p-5">
                <div className="flex items-start justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="font-mono text-xs text-muted-foreground">{project.code}</span>
                            <StatusBadge status={project.status} />
                        </div>
                        <h1 className="mt-1 text-2xl font-semibold">{project.name}</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {BILLING_LABELS[project.billing_type]}
                            {project.contract_value && <> · Contract: {formatPhp(project.contract_value)}</>}
                            {project.budget_hours && <> · Budget: {project.budget_hours} hrs</>}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <a href={`#/projects/${projectId}/edit`}
                           className="rounded-md border px-3 py-1.5 text-sm hover:bg-accent">
                            Edit
                        </a>
                        <a href="#/projects" className="rounded-md border px-3 py-1.5 text-sm hover:bg-accent">
                            ← Back
                        </a>
                    </div>
                </div>
            </div>

            <TimesheetsSection projectId={projectId} />
            <WipSection projectId={projectId} />

            {/* Close Project */}
            {project.status === 'active' && (
                <div className="rounded-lg border border-destructive/20 bg-destructive/5 p-4">
                    <p className="text-sm font-medium text-destructive">Danger Zone</p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Closing the project marks it completed. This requires MFA verification.
                    </p>
                    <button
                        type="button"
                        onClick={() => setShowCloseConfirm(true)}
                        className="mt-3 rounded-md border border-destructive/30 px-3 py-1.5 text-sm text-destructive hover:bg-destructive/10"
                    >
                        Close Project
                    </button>
                    {showCloseConfirm && (
                        <ConfirmClose
                            isPending={closeProject.isPending}
                            onConfirm={() => closeProject.mutate(undefined, { onSuccess: () => setShowCloseConfirm(false) })}
                            onCancel={() => setShowCloseConfirm(false)}
                        />
                    )}
                </div>
            )}
        </div>
    );
}

const BILLING_LABELS = {
    fixed_price: 'Fixed Price', time_and_materials: 'Time & Materials', retainer: 'Retainer',
} as const;

const STATUS_STYLES = {
    draft: 'bg-slate-100 text-slate-700', active: 'bg-emerald-100 text-emerald-800',
    on_hold: 'bg-amber-100 text-amber-800', completed: 'bg-blue-100 text-blue-800',
    cancelled: 'bg-red-100 text-red-700',
} as const;

function StatusBadge({ status }: { status: string }) {
    const cls = STATUS_STYLES[status as keyof typeof STATUS_STYLES] ?? 'bg-slate-100 text-slate-700';
    return <span className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${cls}`}>{status.replace('_', ' ')}</span>;
}

function ConfirmClose({ isPending, onConfirm, onCancel }: { isPending: boolean; onConfirm: () => void; onCancel: () => void }) {
    return (
        <div className="mt-3 flex items-center gap-3 rounded-md border bg-background p-3 text-sm">
            <span>Are you sure? This cannot be undone.</span>
            <button type="button" onClick={onConfirm} disabled={isPending}
                className="rounded-md bg-destructive px-3 py-1 text-xs text-destructive-foreground hover:opacity-90 disabled:opacity-50">
                {isPending ? 'Closing…' : 'Confirm Close'}
            </button>
            <button type="button" onClick={onCancel} className="rounded-md border px-3 py-1 text-xs hover:bg-accent">
                Cancel
            </button>
        </div>
    );
}

function TimesheetsSection({ projectId }: { projectId: string }) {
    const { data: entries = [], isLoading } = useProjectTimesheets(projectId);
    const log = useLogTimesheet();
    const [form, setForm] = useState({ employee_id: '', work_date: '', hours: '', billable_rate: '', description: '' });
    const [err, setErr] = useState<string | null>(null);

    const columns: Column<TimesheetEntry>[] = [
        { key: 'work_date', header: 'Date', render: (e) => e.work_date },
        { key: 'employee', header: 'Employee', render: (e) => <span className="font-mono text-xs">{e.employee_id.slice(0, 8)}…</span> },
        { key: 'hours', header: 'Hours', align: 'right', numeric: true, render: (e) => e.hours },
        { key: 'amount', header: 'Billable', align: 'right', numeric: true, render: (e) => formatPhp(e.billable_amount) },
        { key: 'billed', header: 'Billed', render: (e) => e.is_billed ? <span className="text-xs text-emerald-700">Yes</span> : <span className="text-xs text-muted-foreground">No</span> },
        { key: 'desc', header: 'Description', render: (e) => <span className="text-xs text-muted-foreground">{e.description ?? '—'}</span> },
    ];

    async function handleLog(e: React.FormEvent) {
        e.preventDefault(); setErr(null);
        try {
            await log.mutateAsync({ project_id: projectId, ...form });
            setForm({ employee_id: '', work_date: '', hours: '', billable_rate: '', description: '' });
        } catch (ex: unknown) {
            setErr((ex as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Failed to log entry.');
        }
    }

    return (
        <section>
            <h2 className="mb-3 text-lg font-semibold">Timesheets</h2>
            <DataTable columns={columns} rows={entries} rowKey={(e) => e.id} isLoading={isLoading} emptyState="No timesheet entries yet." />
            <form onSubmit={handleLog} className="mt-4 rounded-lg border bg-card p-4">
                <p className="mb-3 text-sm font-medium">Log New Entry</p>
                {err && <p className="mb-2 text-xs text-destructive">{err}</p>}
                <div className="flex flex-wrap gap-3">
                    <input type="text" placeholder="Employee UUID" required value={form.employee_id}
                        onChange={(e) => setForm((f) => ({ ...f, employee_id: e.target.value }))}
                        className="rounded-md border bg-background px-3 py-2 text-sm w-52" />
                    <input type="date" required value={form.work_date}
                        onChange={(e) => setForm((f) => ({ ...f, work_date: e.target.value }))}
                        className="rounded-md border bg-background px-3 py-2 text-sm" />
                    <input type="number" placeholder="Hours" required min="0.25" max="24" step="0.25" value={form.hours}
                        onChange={(e) => setForm((f) => ({ ...f, hours: e.target.value }))}
                        className="rounded-md border bg-background px-3 py-2 text-sm w-24" />
                    <input type="number" placeholder="Rate" min="0" step="0.01" value={form.billable_rate}
                        onChange={(e) => setForm((f) => ({ ...f, billable_rate: e.target.value }))}
                        className="rounded-md border bg-background px-3 py-2 text-sm w-28" />
                    <input type="text" placeholder="Description (optional)" value={form.description}
                        onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                        className="rounded-md border bg-background px-3 py-2 text-sm flex-1" />
                    <button type="submit" disabled={log.isPending}
                        className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50">
                        {log.isPending ? 'Logging…' : 'Log Entry'}
                    </button>
                </div>
            </form>
        </section>
    );
}

function WipSection({ projectId }: { projectId: string }) {
    const { data: entries = [], isLoading: _loading } = useProjectTimesheets(projectId);
    const recognizeWip = useRecognizeWip(projectId);
    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState({ period_from: '', period_to: '', wip_account_id: '', revenue_account_id: '' });
    const [err, setErr] = useState<string | null>(null);

    const unbilledHours = entries.filter((e) => !e.is_billed).reduce((sum, e) => {
        return (Number(sum) + Number(e.hours)).toFixed(2);
    }, '0.00');

    async function handleRecognize(e: React.FormEvent) {
        e.preventDefault(); setErr(null);
        try {
            await recognizeWip.mutateAsync(form);
            setShowForm(false);
        } catch (ex: unknown) {
            setErr((ex as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Failed.');
        }
    }

    return (
        <section>
            <div className="mb-3 flex items-center justify-between">
                <h2 className="text-lg font-semibold">WIP Recognition</h2>
                <button type="button" onClick={() => setShowForm((v) => !v)}
                    className="rounded-md border px-3 py-1.5 text-sm hover:bg-accent">
                    {showForm ? 'Cancel' : 'Recognize WIP'}
                </button>
            </div>

            <div className="mb-4 inline-block rounded-lg border bg-card p-3">
                <div className="text-xs uppercase tracking-wide text-muted-foreground">Unbilled Hours</div>
                <div className="mt-1 text-xl tabular-nums font-semibold">{unbilledHours}</div>
            </div>

            {showForm && (
                <form onSubmit={handleRecognize} className="rounded-lg border bg-card p-4 space-y-3">
                    {err && <p className="text-xs text-destructive">{err}</p>}
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="block text-xs font-medium uppercase tracking-wide text-muted-foreground">Period From</label>
                            <input type="date" required value={form.period_from}
                                onChange={(e) => setForm((f) => ({ ...f, period_from: e.target.value }))}
                                className="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label className="block text-xs font-medium uppercase tracking-wide text-muted-foreground">Period To</label>
                            <input type="date" required value={form.period_to}
                                onChange={(e) => setForm((f) => ({ ...f, period_to: e.target.value }))}
                                className="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm" />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="block text-xs font-medium uppercase tracking-wide text-muted-foreground">WIP Account UUID</label>
                            <input type="text" required placeholder="UUID" value={form.wip_account_id}
                                onChange={(e) => setForm((f) => ({ ...f, wip_account_id: e.target.value }))}
                                className="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm font-mono" />
                        </div>
                        <div>
                            <label className="block text-xs font-medium uppercase tracking-wide text-muted-foreground">Revenue Account UUID</label>
                            <input type="text" required placeholder="UUID" value={form.revenue_account_id}
                                onChange={(e) => setForm((f) => ({ ...f, revenue_account_id: e.target.value }))}
                                className="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm font-mono" />
                        </div>
                    </div>
                    <div className="flex justify-end">
                        <button type="submit" disabled={recognizeWip.isPending}
                            className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50">
                            {recognizeWip.isPending ? 'Posting…' : 'Post WIP Entry'}
                        </button>
                    </div>
                </form>
            )}
        </section>
    );
}
