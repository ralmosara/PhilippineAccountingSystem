import { useState } from 'react';

import { DataTable, type Column } from '@/shared/components/DataTable';
import { formatPhp } from '@/shared/lib/money';

import {
    MOVEMENT_TYPE_LABELS,
    useStockMovements,
    useWarehouses,
    type StockMovement,
} from '../api/inventory';

/**
 * Stock movement register — the BIR-required audit trail for inventory.
 * Each row shows the resulting moving-average cost AFTER the movement
 * was applied, which is what the next issue draws against.
 */
export function StockMovementsPage() {
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');
    const [warehouseId, setWarehouseId] = useState('');

    const { data: movements, isLoading } = useStockMovements({
        from: from || undefined,
        to: to || undefined,
        warehouse_id: warehouseId || undefined,
    });
    const { data: warehouses } = useWarehouses();

    const columns: Column<StockMovement>[] = [
        { key: 'moved_at', header: 'Date', numeric: true },
        {
            key: 'item',
            header: 'Item',
            render: (m) =>
                m.item_sku ? (
                    <a
                        href={`#/inventory/items/${m.item_id}`}
                        className="text-primary hover:underline"
                    >
                        <span className="font-mono text-xs">{m.item_sku}</span>
                        {m.item_name && <span className="ml-1.5">{m.item_name}</span>}
                    </a>
                ) : (
                    <span className="text-muted-foreground">{m.item_id.slice(0, 8)}…</span>
                ),
        },
        {
            key: 'warehouse',
            header: 'Warehouse',
            render: (m) => (
                <span className="text-xs text-muted-foreground">
                    {m.warehouse_name ?? m.warehouse_id.slice(0, 8) + '…'}
                </span>
            ),
        },
        {
            key: 'type',
            header: 'Type',
            render: (m) => <MovementTypeBadge type={m.movement_type} />,
        },
        {
            key: 'qty',
            header: 'Qty',
            align: 'right',
            numeric: true,
            render: (m) => {
                const isOut = m.movement_type === 'issue' || m.movement_type === 'transfer_out';
                const display = Number(m.quantity).toLocaleString('en-PH', {
                    maximumFractionDigits: 4,
                });
                return (
                    <span className={isOut ? 'text-amber-700' : ''}>
                        {isOut ? `(${display})` : display}
                    </span>
                );
            },
        },
        {
            key: 'unit_cost',
            header: 'Unit cost',
            align: 'right',
            numeric: true,
            render: (m) => formatPhp(m.unit_cost),
        },
        {
            key: 'total_cost',
            header: 'Total cost',
            align: 'right',
            numeric: true,
            render: (m) => formatPhp(m.total_cost),
        },
        {
            key: 'resulting',
            header: 'Resulting MA',
            align: 'right',
            numeric: true,
            render: (m) => (
                <div className="text-xs">
                    <div>{formatPhp(m.resulting_ma_cost)}</div>
                    <div className="text-muted-foreground">
                        {Number(m.resulting_quantity).toLocaleString('en-PH', { maximumFractionDigits: 4 })} on hand
                    </div>
                </div>
            ),
        },
        {
            key: 'source',
            header: 'Source',
            render: (m) =>
                m.source_doc_type ? (
                    <span className="text-xs text-muted-foreground">
                        {m.source_doc_type} ·{' '}
                        {m.source_doc_id ? (
                            <span className="font-mono">{m.source_doc_id.slice(0, 8)}…</span>
                        ) : (
                            '—'
                        )}
                    </span>
                ) : (
                    '—'
                ),
        },
    ];

    return (
        <div className="container py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">Stock Movements</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        BIR-required inventory audit trail. Every receipt, issue, transfer, and
                        adjustment is logged with the resulting moving-average cost. Negative-stock
                        movements are refused at the action layer (no overdrafts).
                    </p>
                </div>

                <div className="flex items-center gap-2 text-sm">
                    <input
                        type="date"
                        value={from}
                        onChange={(e) => setFrom(e.target.value)}
                        className="rounded-md border bg-background px-2 py-1"
                    />
                    <input
                        type="date"
                        value={to}
                        onChange={(e) => setTo(e.target.value)}
                        className="rounded-md border bg-background px-2 py-1"
                    />
                    <select
                        value={warehouseId}
                        onChange={(e) => setWarehouseId(e.target.value)}
                        className="rounded-md border bg-background px-2 py-1"
                    >
                        <option value="">All warehouses</option>
                        {warehouses?.map((w) => (
                            <option key={w.id} value={w.id}>
                                {w.code} · {w.name}
                            </option>
                        ))}
                    </select>
                    <a
                        href="#/inventory/items"
                        className="rounded-md border px-3 py-1.5 hover:bg-accent"
                    >
                        View items
                    </a>
                </div>
            </header>

            <div className="mt-6">
                <DataTable
                    columns={columns}
                    rows={movements}
                    rowKey={(m) => m.id}
                    isLoading={isLoading}
                    emptyState="No stock movements in the selected range."
                />
            </div>
        </div>
    );
}

function MovementTypeBadge({ type }: { type: StockMovement['movement_type'] }) {
    const styles: Record<StockMovement['movement_type'], string> = {
        receipt:        'bg-emerald-100 text-emerald-800',
        issue:          'bg-amber-100 text-amber-800',
        transfer_out:   'bg-orange-100 text-orange-800',
        transfer_in:    'bg-blue-100 text-blue-800',
        adjustment:     'bg-slate-100 text-slate-700',
    };
    return (
        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${styles[type]}`}>
            {MOVEMENT_TYPE_LABELS[type]}
        </span>
    );
}
