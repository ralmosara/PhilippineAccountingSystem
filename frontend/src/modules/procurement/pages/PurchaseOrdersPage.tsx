import { useState } from 'react';

import { DataTable, type Column } from '@/shared/components/DataTable';
import { formatPhp } from '@/shared/lib/money';

import { usePurchaseOrders, type PurchaseOrder } from '../api/purchase-orders';

const STATUS_COLOURS: Record<PurchaseOrder['status'], string> = {
    draft:      'bg-slate-100 text-slate-700',
    approved:   'bg-blue-100 text-blue-800',
    sent:       'bg-indigo-100 text-indigo-800',
    partial:    'bg-amber-100 text-amber-800',
    fulfilled:  'bg-emerald-100 text-emerald-800',
    cancelled:  'bg-red-100 text-red-800',
};

export function PurchaseOrdersPage() {
    const [status, setStatus] = useState('');

    const { data: orders, isLoading } = usePurchaseOrders({
        status: status || undefined,
    });

    const columns: Column<PurchaseOrder>[] = [
        {
            key: 'po_no',
            header: 'PO #',
            render: (po) => (
                <a
                    href={`#/procurement/purchase-orders/${po.id}`}
                    className="font-mono text-primary hover:underline"
                >
                    {po.po_no}
                </a>
            ),
        },
        {
            key: 'order_date',
            header: 'Order Date',
            numeric: true,
            render: (po) => po.order_date ?? '—',
        },
        {
            key: 'delivery',
            header: 'Expected Delivery',
            numeric: true,
            render: (po) => po.expected_delivery ?? '—',
        },
        {
            key: 'total',
            header: 'Total',
            align: 'right',
            numeric: true,
            render: (po) => formatPhp(po.total),
        },
        {
            key: 'lines',
            header: 'Lines',
            align: 'right',
            numeric: true,
            render: (po) => po.lines.length,
        },
        {
            key: 'status',
            header: 'Status',
            render: (po) => (
                <span
                    className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${STATUS_COLOURS[po.status]}`}
                >
                    {po.status}
                </span>
            ),
        },
    ];

    return (
        <div className="container py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">Purchase Orders</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        PO lifecycle: draft → approved → sent → partial/fulfilled.
                        Approved POs feed the goods receipt and vendor bill matching flow.
                    </p>
                </div>

                <div className="flex items-center gap-2 text-sm">
                    <select
                        value={status}
                        onChange={(e) => setStatus(e.target.value)}
                        className="rounded-md border bg-background px-2 py-1"
                    >
                        <option value="">All statuses</option>
                        <option value="draft">Draft</option>
                        <option value="approved">Approved</option>
                        <option value="sent">Sent</option>
                        <option value="partial">Partial</option>
                        <option value="fulfilled">Fulfilled</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </header>

            <div className="mt-6">
                <DataTable
                    columns={columns}
                    rows={orders}
                    rowKey={(po) => po.id}
                    isLoading={isLoading}
                    emptyState="No purchase orders match the current filters."
                />
            </div>

            <div className="mt-4 rounded-md border border-dashed bg-muted/30 p-3 text-xs text-muted-foreground">
                Purchase order creation (CreatePurchaseOrder action) is pending backend
                implementation. PO data from imported records is viewable here.
            </div>
        </div>
    );
}
