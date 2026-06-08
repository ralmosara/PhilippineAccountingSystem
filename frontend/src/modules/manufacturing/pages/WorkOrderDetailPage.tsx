import { useState } from 'react';

import { DataTable, type Column } from '@/shared/components/DataTable';
import { formatPhp } from '@/shared/lib/money';

import {
    useWorkOrder,
    useStartWorkOrder,
    useCompleteProductionRun,
    useCancelWorkOrder,
    type WorkOrderLine,
} from '../api/manufacturing';

interface Props {
    workOrderId: string;
}

const STATUS_TAILWIND: Record<string, string> = {
    draft:       'rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700',
    released:    'rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700',
    in_progress: 'rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700',
    completed:   'rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800',
    cancelled:   'rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700',
};

export function WorkOrderDetailPage({ workOrderId }: Props) {
    const { data: wo, isLoading } = useWorkOrder(workOrderId);
    const startMutation    = useStartWorkOrder(workOrderId);
    const completeMutation = useCompleteProductionRun(workOrderId);
    const cancelMutation   = useCancelWorkOrder(workOrderId);

    const [qtyProduced, setQtyProduced] = useState('');
    const [showCompleteForm, setShowCompleteForm] = useState(false);

    const lineColumns: Column<WorkOrderLine>[] = [
        {
            key: 'component_name',
            header: 'Component',
            render: (l) => <span className="font-medium">{l.component_name}</span>,
        },
        {
            key: 'quantity_required',
            header: 'Qty Required',
            align: 'right',
            numeric: true,
            render: (l) =>
                Number(l.quantity_required).toLocaleString('en-PH', { maximumFractionDigits: 4 }),
        },
        {
            key: 'quantity_consumed',
            header: 'Qty Consumed',
            align: 'right',
            numeric: true,
            render: (l) =>
                Number(l.quantity_consumed) > 0
                    ? Number(l.quantity_consumed).toLocaleString('en-PH', { maximumFractionDigits: 4 })
                    : <span className="text-muted-foreground">—</span>,
        },
        {
            key: 'unit_cost',
            header: 'Unit Cost',
            align: 'right',
            numeric: true,
            render: (l) => (Number(l.unit_cost) > 0 ? formatPhp(l.unit_cost) : <span className="text-muted-foreground">—</span>),
        },
        {
            key: 'total_cost',
            header: 'Total Cost',
            align: 'right',
            numeric: true,
            render: (l) => (Number(l.total_cost) > 0 ? formatPhp(l.total_cost) : <span className="text-muted-foreground">—</span>),
        },
    ];

    if (isLoading) {
        return <div className="container py-8 text-muted-foreground">Loading work order…</div>;
    }

    if (!wo) {
        return <div className="container py-8 text-destructive">Work order not found.</div>;
    }

    const canStart    = wo.status === 'draft' || wo.status === 'released';
    const canComplete = wo.status === 'in_progress' || wo.status === 'released';
    const canCancel   = wo.status !== 'completed' && wo.status !== 'cancelled';

    const handleStart = () => {
        if (!confirm('Start this work order? This will set status to In Progress.')) return;
        startMutation.mutate();
    };

    const handleComplete = () => {
        if (!qtyProduced || Number(qtyProduced) <= 0) {
            alert('Enter a valid quantity produced.');
            return;
        }
        // MFA visual acknowledgement
        if (!confirm(`Complete production run?\n\nQuantity produced: ${qtyProduced}\n\nThis will consume raw materials, update stock balances, and post a journal entry. This action requires MFA verification.`)) {
            return;
        }
        completeMutation.mutate(
            { quantity_produced: qtyProduced },
            { onSuccess: () => setShowCompleteForm(false) },
        );
    };

    const handleCancel = () => {
        if (!confirm('Cancel this work order? This cannot be undone.')) return;
        cancelMutation.mutate();
    };

    return (
        <div className="container py-8 space-y-6">
            {/* Header */}
            <div className="flex items-start justify-between">
                <div>
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-semibold font-mono">{wo.work_order_no}</h1>
                        <span className={STATUS_TAILWIND[wo.status] ?? STATUS_TAILWIND.draft}>
                            {wo.status_label}
                        </span>
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        BOM:{' '}
                        <a href={`#/manufacturing/boms/${wo.bom_id}`} className="text-primary hover:underline font-mono">
                            {wo.bom_id.slice(0, 8)}…
                        </a>
                    </p>
                </div>

                <div className="flex gap-2">
                    {canStart && (
                        <button
                            onClick={handleStart}
                            disabled={startMutation.isPending}
                            className="rounded-md bg-amber-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-600 disabled:opacity-50"
                        >
                            {startMutation.isPending ? 'Starting…' : 'Start'}
                        </button>
                    )}
                    {canComplete && !showCompleteForm && (
                        <button
                            onClick={() => setShowCompleteForm(true)}
                            className="rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700"
                        >
                            Complete
                        </button>
                    )}
                    {canCancel && (
                        <button
                            onClick={handleCancel}
                            disabled={cancelMutation.isPending}
                            className="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 disabled:opacity-50"
                        >
                            {cancelMutation.isPending ? 'Cancelling…' : 'Cancel'}
                        </button>
                    )}
                    <a
                        href="#/manufacturing/work-orders"
                        className="rounded-md border px-3 py-1.5 text-sm hover:bg-accent"
                    >
                        Back to list
                    </a>
                </div>
            </div>

            {/* Complete production form (MFA visual) */}
            {showCompleteForm && canComplete && (
                <div className="rounded-lg border border-emerald-300 bg-emerald-50 p-4 space-y-3">
                    <p className="text-sm font-medium text-emerald-900">
                        Complete Production Run
                    </p>
                    <p className="text-xs text-emerald-700">
                        This action requires MFA verification. It will consume raw materials,
                        update stock balances, and post a journal entry.
                    </p>
                    <div className="flex items-end gap-3">
                        <div>
                            <label className="block text-xs font-medium text-emerald-900 mb-1">
                                Quantity Produced
                            </label>
                            <input
                                type="number"
                                step="0.0001"
                                min="0.0001"
                                value={qtyProduced}
                                onChange={(e) => setQtyProduced(e.target.value)}
                                placeholder={wo.quantity_to_produce}
                                className="rounded-md border px-2 py-1.5 text-sm w-32"
                            />
                        </div>
                        <button
                            onClick={handleComplete}
                            disabled={completeMutation.isPending}
                            className="rounded-md bg-emerald-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                        >
                            {completeMutation.isPending ? 'Posting…' : 'Confirm & Post'}
                        </button>
                        <button
                            onClick={() => setShowCompleteForm(false)}
                            className="rounded-md border px-3 py-1.5 text-sm hover:bg-accent"
                        >
                            Cancel
                        </button>
                    </div>
                    {completeMutation.isError && (
                        <p className="text-xs text-red-600">
                            {(completeMutation.error as Error)?.message ?? 'An error occurred.'}
                        </p>
                    )}
                </div>
            )}

            {/* Quantity summary */}
            <div className="grid grid-cols-4 gap-4">
                <div className="rounded-lg border bg-card p-4">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">To Produce</p>
                    <p className="mt-1 text-xl font-semibold tabular-nums">
                        {Number(wo.quantity_to_produce).toLocaleString('en-PH', { maximumFractionDigits: 4 })}
                    </p>
                </div>
                <div className="rounded-lg border bg-card p-4">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Produced</p>
                    <p className="mt-1 text-xl font-semibold tabular-nums">
                        {Number(wo.quantity_produced) > 0
                            ? Number(wo.quantity_produced).toLocaleString('en-PH', { maximumFractionDigits: 4 })
                            : '—'}
                    </p>
                </div>
                <div className="rounded-lg border bg-card p-4">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Material Cost</p>
                    <p className="mt-1 text-xl font-semibold tabular-nums">
                        {wo.status === 'completed' ? formatPhp(wo.total_material_cost) : '—'}
                    </p>
                </div>
                <div className="rounded-lg border bg-card p-4">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Total Production Cost</p>
                    <p className="mt-1 text-xl font-semibold tabular-nums">
                        {wo.status === 'completed' ? formatPhp(wo.total_production_cost) : '—'}
                    </p>
                </div>
            </div>

            {/* Scheduling */}
            {(wo.scheduled_start || wo.actual_start) && (
                <div className="grid grid-cols-2 gap-4 text-sm">
                    <div className="rounded-lg border bg-card p-4">
                        <p className="text-xs uppercase tracking-wide text-muted-foreground mb-1">Scheduled</p>
                        <p>{wo.scheduled_start ?? '—'} → {wo.scheduled_end ?? '—'}</p>
                    </div>
                    <div className="rounded-lg border bg-card p-4">
                        <p className="text-xs uppercase tracking-wide text-muted-foreground mb-1">Actual</p>
                        <p>
                            {wo.actual_start ? new Date(wo.actual_start).toLocaleString('en-PH') : '—'}
                            {' → '}
                            {wo.actual_end ? new Date(wo.actual_end).toLocaleString('en-PH') : '—'}
                        </p>
                    </div>
                </div>
            )}

            {/* Production run lines */}
            <section>
                <h2 className="mb-3 text-lg font-medium">Production Run Lines</h2>
                <DataTable
                    columns={lineColumns}
                    rows={wo.lines ?? []}
                    rowKey={(l) => l.id}
                    emptyState="No component lines attached to this work order."
                />
            </section>

            {/* Journal entry link */}
            {wo.journal_entry_id && (
                <div className="rounded-lg border bg-muted/30 p-4 text-sm">
                    <span className="text-muted-foreground">Journal Entry: </span>
                    <a
                        href={`#/accounting/journals/${wo.journal_entry_id}`}
                        className="font-mono text-primary hover:underline"
                    >
                        {wo.journal_entry_id}
                    </a>
                </div>
            )}
        </div>
    );
}
