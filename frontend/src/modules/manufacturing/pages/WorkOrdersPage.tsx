import { useState } from 'react';

import { DataTable, type Column } from '@/shared/components/DataTable';
import { formatPhp } from '@/shared/lib/money';

import { useWorkOrders, type WorkOrder } from '../api/manufacturing';

const STATUS_TAILWIND: Record<WorkOrder['status'], string> = {
    draft:       'rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700',
    released:    'rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700',
    in_progress: 'rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700',
    completed:   'rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800',
    cancelled:   'rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700',
};

export function WorkOrdersPage() {
    const [statusFilter, setStatusFilter] = useState<string>('');

    const { data: workOrders, isLoading } = useWorkOrders({
        status: statusFilter || undefined,
    });

    const columns: Column<WorkOrder>[] = [
        {
            key: 'work_order_no',
            header: 'WO No.',
            render: (wo) => (
                <a
                    href={`#/manufacturing/work-orders/${wo.id}`}
                    className="font-mono text-primary hover:underline"
                >
                    {wo.work_order_no}
                </a>
            ),
        },
        {
            key: 'bom_id',
            header: 'BOM',
            render: (wo) => (
                <span className="text-xs text-muted-foreground font-mono">
                    {wo.bom_id.slice(0, 8)}…
                </span>
            ),
        },
        {
            key: 'quantity_to_produce',
            header: 'Qty to Produce',
            align: 'right',
            numeric: true,
            render: (wo) =>
                Number(wo.quantity_to_produce).toLocaleString('en-PH', { maximumFractionDigits: 4 }),
        },
        {
            key: 'status',
            header: 'Status',
            render: (wo) => (
                <span className={STATUS_TAILWIND[wo.status]}>{wo.status_label}</span>
            ),
        },
        {
            key: 'scheduled_start',
            header: 'Scheduled',
            render: (wo) => {
                if (!wo.scheduled_start) return <span className="text-muted-foreground">—</span>;
                return (
                    <span className="text-sm">
                        {wo.scheduled_start}
                        {wo.scheduled_end ? ` → ${wo.scheduled_end}` : ''}
                    </span>
                );
            },
        },
        {
            key: 'total_production_cost',
            header: 'Production Cost',
            align: 'right',
            numeric: true,
            render: (wo) =>
                wo.status === 'completed'
                    ? formatPhp(wo.total_production_cost)
                    : <span className="text-muted-foreground">—</span>,
        },
    ];

    return (
        <div className="container py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">Work Orders</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Track production runs from draft through completion.
                        Completing a work order consumes raw materials, updates stock
                        balances, and posts a journal entry.
                    </p>
                </div>

                <div className="flex items-center gap-3 text-sm">
                    <select
                        value={statusFilter}
                        onChange={(e) => setStatusFilter(e.target.value)}
                        className="rounded-md border bg-background px-2 py-1 text-sm"
                    >
                        <option value="">All statuses</option>
                        <option value="draft">Draft</option>
                        <option value="released">Released</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <a
                        href="#/manufacturing/work-orders/new"
                        className="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                    >
                        New Work Order
                    </a>
                </div>
            </header>

            <div className="mt-6">
                <DataTable
                    columns={columns}
                    rows={workOrders}
                    rowKey={(wo) => wo.id}
                    isLoading={isLoading}
                    emptyState="No work orders found. Create one from an active BOM."
                />
            </div>
        </div>
    );
}
