import { useState } from 'react';

import { DataTable, type Column } from '@/shared/components/DataTable';

import { useBoms, type Bom } from '../api/manufacturing';

const STATUS_BADGE: Record<Bom['status'], string> = {
    draft:      'rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700',
    active:     'rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800',
    superseded: 'rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700',
};

export function BillOfMaterialsPage() {
    const [statusFilter, setStatusFilter] = useState<string>('');
    const [query, setQuery] = useState('');

    const { data: boms, isLoading } = useBoms({
        status: statusFilter || undefined,
        q: query || undefined,
    });

    const columns: Column<Bom>[] = [
        {
            key: 'code',
            header: 'Code',
            render: (b) => (
                <a
                    href={`#/manufacturing/boms/${b.id}`}
                    className="font-mono text-primary hover:underline"
                >
                    {b.code}
                </a>
            ),
        },
        { key: 'name', header: 'Name' },
        {
            key: 'item_name',
            header: 'Finished Good',
            render: (b) => <span className="text-muted-foreground">{b.item_name}</span>,
        },
        {
            key: 'standard_batch_size',
            header: 'Batch Size',
            align: 'right',
            numeric: true,
            render: (b) =>
                Number(b.standard_batch_size).toLocaleString('en-PH', { maximumFractionDigits: 4 }),
        },
        {
            key: 'version',
            header: 'Version',
            render: (b) => <span className="text-xs text-muted-foreground">v{b.version}</span>,
        },
        {
            key: 'status',
            header: 'Status',
            render: (b) => <span className={STATUS_BADGE[b.status]}>{b.status}</span>,
        },
    ];

    return (
        <div className="container py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">Bills of Materials</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Define the components and costs required to produce one batch of a
                        finished good. Activate a BOM to make it available for work orders.
                    </p>
                </div>

                <div className="flex items-center gap-3 text-sm">
                    <input
                        type="search"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search code / name"
                        className="rounded-md border bg-background px-2 py-1"
                    />
                    <select
                        value={statusFilter}
                        onChange={(e) => setStatusFilter(e.target.value)}
                        className="rounded-md border bg-background px-2 py-1 text-sm"
                    >
                        <option value="">All statuses</option>
                        <option value="draft">Draft</option>
                        <option value="active">Active</option>
                        <option value="superseded">Superseded</option>
                    </select>
                    <a
                        href="#/manufacturing/boms/new"
                        className="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                    >
                        New BOM
                    </a>
                </div>
            </header>

            <div className="mt-6">
                <DataTable
                    columns={columns}
                    rows={boms}
                    rowKey={(b) => b.id}
                    isLoading={isLoading}
                    emptyState="No bills of materials found. Create one to get started."
                />
            </div>
        </div>
    );
}
