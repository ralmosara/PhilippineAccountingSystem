import { DataTable, type Column } from '@/shared/components/DataTable';
import { formatPhp } from '@/shared/lib/money';

import { useBom, type BomLine } from '../api/manufacturing';

interface Props {
    bomId: string;
}

const STATUS_BADGE: Record<string, string> = {
    draft:      'rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700',
    active:     'rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800',
    superseded: 'rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700',
};

export function BomDetailPage({ bomId }: Props) {
    const { data: bom, isLoading } = useBom(bomId);

    const lineColumns: Column<BomLine>[] = [
        {
            key: 'component_name',
            header: 'Component',
            render: (l) => <span className="font-medium">{l.component_name}</span>,
        },
        {
            key: 'quantity_per_batch',
            header: 'Qty / Batch',
            align: 'right',
            numeric: true,
            render: (l) =>
                Number(l.quantity_per_batch).toLocaleString('en-PH', { maximumFractionDigits: 4 }),
        },
        {
            key: 'unit_of_measure',
            header: 'UOM',
            render: (l) => (
                <span className="text-xs uppercase tracking-wide text-muted-foreground">
                    {l.unit_of_measure}
                </span>
            ),
        },
        {
            key: 'notes',
            header: 'Notes',
            render: (l) => <span className="text-muted-foreground">{l.notes ?? '—'}</span>,
        },
    ];

    if (isLoading) {
        return (
            <div className="container py-8 text-muted-foreground">Loading BOM…</div>
        );
    }

    if (!bom) {
        return (
            <div className="container py-8 text-destructive">BOM not found.</div>
        );
    }

    return (
        <div className="container py-8 space-y-6">
            {/* Header */}
            <div className="flex items-start justify-between">
                <div>
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-semibold font-mono">{bom.code}</h1>
                        <span className={STATUS_BADGE[bom.status] ?? STATUS_BADGE.draft}>
                            {bom.status}
                        </span>
                        <span className="text-xs text-muted-foreground">v{bom.version}</span>
                    </div>
                    <p className="mt-1 text-lg text-muted-foreground">{bom.name}</p>
                    <p className="mt-0.5 text-sm text-muted-foreground">
                        Finished good: <strong>{bom.item_name}</strong>
                    </p>
                </div>

                <div className="flex gap-2">
                    {bom.status === 'draft' && (
                        <button
                            onClick={() => {
                                /* TODO: call useActivateBom mutation */
                                alert('Activate BOM — wire useActivateBom mutation here.');
                            }}
                            className="rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700"
                        >
                            Activate BOM
                        </button>
                    )}
                    <a
                        href="#/manufacturing/boms"
                        className="rounded-md border px-3 py-1.5 text-sm hover:bg-accent"
                    >
                        Back to list
                    </a>
                </div>
            </div>

            {/* Batch cost summary */}
            <div className="grid grid-cols-3 gap-4">
                <div className="rounded-lg border bg-card p-4">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Standard Batch Size</p>
                    <p className="mt-1 text-xl font-semibold tabular-nums">
                        {Number(bom.standard_batch_size).toLocaleString('en-PH', { maximumFractionDigits: 4 })}
                        <span className="ml-1 text-sm font-normal text-muted-foreground">units</span>
                    </p>
                </div>
                <div className="rounded-lg border bg-card p-4">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Labor / Batch</p>
                    <p className="mt-1 text-xl font-semibold tabular-nums">
                        {formatPhp(bom.labor_cost_per_batch)}
                    </p>
                </div>
                <div className="rounded-lg border bg-card p-4">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Overhead / Batch</p>
                    <p className="mt-1 text-xl font-semibold tabular-nums">
                        {formatPhp(bom.overhead_cost_per_batch)}
                    </p>
                </div>
            </div>

            {/* Component lines */}
            <section>
                <h2 className="mb-3 text-lg font-medium">Component Lines</h2>
                <DataTable
                    columns={lineColumns}
                    rows={bom.lines ?? []}
                    rowKey={(l) => l.id}
                    emptyState="No component lines defined."
                />
            </section>

            {/* Notes */}
            {bom.notes && (
                <section>
                    <h2 className="mb-2 text-base font-medium">Notes</h2>
                    <p className="rounded-lg border bg-muted/30 p-4 text-sm text-muted-foreground whitespace-pre-wrap">
                        {bom.notes}
                    </p>
                </section>
            )}
        </div>
    );
}
