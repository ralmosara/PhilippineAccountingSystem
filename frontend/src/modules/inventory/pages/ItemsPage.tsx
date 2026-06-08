import { useState } from 'react';

import { DataTable, type Column } from '@/shared/components/DataTable';
import { formatPhp } from '@/shared/lib/money';

import { useItems, type Item } from '../api/inventory';

export function ItemsPage() {
    const [query, setQuery] = useState('');
    const [activeOnly, setActiveOnly] = useState(true);

    const { data: items, isLoading } = useItems({
        q: query || undefined,
        active_only: activeOnly,
    });

    const totalInventoryValue = (items ?? []).reduce(
        (sum, i) => sum + Number(i.total_value || 0),
        0,
    );

    const columns: Column<Item>[] = [
        {
            key: 'sku',
            header: 'SKU',
            render: (i) => (
                <a href={`#/inventory/items/${i.id}`} className="font-mono text-primary hover:underline">
                    {i.sku}
                </a>
            ),
        },
        { key: 'name', header: 'Name' },
        {
            key: 'costing',
            header: 'Costing',
            render: (i) => (
                <span className="text-xs uppercase tracking-wide text-muted-foreground">
                    {i.costing_method === 'moving_average' ? 'MA' : i.costing_method}
                </span>
            ),
        },
        {
            key: 'qty',
            header: 'On hand',
            align: 'right',
            numeric: true,
            render: (i) => Number(i.total_quantity).toLocaleString('en-PH', { maximumFractionDigits: 4 }),
        },
        {
            key: 'ma',
            header: 'MA unit cost',
            align: 'right',
            numeric: true,
            render: (i) => formatPhp(i.moving_avg_cost),
        },
        {
            key: 'value',
            header: 'Total value',
            align: 'right',
            numeric: true,
            render: (i) => formatPhp(i.total_value),
        },
        {
            key: 'status',
            header: 'Status',
            render: (i) =>
                i.is_active ? (
                    <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">
                        Active
                    </span>
                ) : (
                    <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                        Inactive
                    </span>
                ),
        },
    ];

    return (
        <div className="container py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">Items</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Inventory master. Quantity + moving-average cost are aggregated across
                        warehouses; click an SKU for the per-warehouse breakdown and movement history.
                    </p>
                </div>

                <div className="flex items-center gap-3 text-sm">
                    <input
                        type="search"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search SKU / name"
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
                    <a
                        href="#/inventory/stock-movements"
                        className="rounded-md border px-3 py-1.5 hover:bg-accent"
                    >
                        View movements
                    </a>
                </div>
            </header>

            {items && items.length > 0 && (
                <div className="mt-4 inline-block rounded-md border bg-card px-4 py-2 text-sm">
                    <span className="text-xs uppercase tracking-wide text-muted-foreground">
                        Total inventory value
                    </span>
                    <span className="ml-3 font-semibold tabular-nums">
                        {formatPhp(totalInventoryValue.toString())}
                    </span>
                    <span className="ml-3 text-xs text-muted-foreground">
                        across {items.length} item{items.length === 1 ? '' : 's'}
                    </span>
                </div>
            )}

            <div className="mt-6">
                <DataTable
                    columns={columns}
                    rows={items}
                    rowKey={(i) => i.id}
                    isLoading={isLoading}
                    emptyState="No items match the current filters."
                />
            </div>
        </div>
    );
}
