import { useState } from 'react';

import { DataTable, type Column } from '@/shared/components/DataTable';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';
import { formatPhp } from '@/shared/lib/money';

import {
    useComputeMonthlyDepreciation,
    useFixedAssets,
    type FixedAsset,
} from '../api/fixed-assets';

export function FixedAssetsPage() {
    const user = useAuthStore((s) => s.user);
    const { data: assets, isLoading } = useFixedAssets();
    const compute = useComputeMonthlyDepreciation();

    const canView     = hasPermission(user, 'assets.view');
    const canRegister = hasPermission(user, 'assets.register');
    const canRunDepr  = hasPermission(user, 'assets.depreciation.run');

    const now = new Date();
    const [deprYear, setDeprYear]   = useState(now.getFullYear());
    const [deprMonth, setDeprMonth] = useState(now.getMonth() + 1);

    if (!canView) {
        return (
            <div className="container py-8">
                <p className="text-muted-foreground">You do not have permission to view fixed assets.</p>
            </div>
        );
    }

    const columns: Column<FixedAsset>[] = [
        {
            key: 'asset_no',
            header: 'Asset No.',
            render: (r) => (
                <a
                    href={`#/fixed-assets/${r.id}`}
                    className="font-mono text-primary hover:underline"
                >
                    {r.asset_no}
                </a>
            ),
        },
        { key: 'name',     header: 'Name',     render: (r) => r.name },
        {
            key: 'category',
            header: 'Category',
            render: (r) => <CategoryBadge category={r.category} label={r.category_label} />,
        },
        {
            key: 'acquisition_date',
            header: 'Acq. Date',
            render: (r) => r.acquisition_date,
        },
        {
            key: 'acquisition_cost',
            header: 'Cost (₱)',
            align: 'right',
            numeric: true,
            render: (r) => formatPhp(r.acquisition_cost),
        },
        {
            key: 'accumulated_depreciation',
            header: 'Accum. Depr. (₱)',
            align: 'right',
            numeric: true,
            render: (r) => formatPhp(r.accumulated_depreciation),
        },
        {
            key: 'book_value',
            header: 'Book Value (₱)',
            align: 'right',
            numeric: true,
            render: (r) => formatPhp(r.book_value),
        },
        {
            key: 'status',
            header: 'Status',
            render: (r) => <AssetStatusBadge status={r.status} />,
        },
    ];

    return (
        <div className="container py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">Fixed Assets</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Property, plant &amp; equipment register. PFRS for SMEs depreciation (SLM / DDB).
                    </p>
                </div>
                {canRegister && (
                    <a
                        href="#/fixed-assets/register"
                        className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90"
                    >
                        Register Asset
                    </a>
                )}
            </header>

            {canRunDepr && (
                <div className="mt-4 flex flex-wrap items-end gap-3 rounded-lg border bg-card p-4">
                    <div>
                        <p className="text-sm font-medium">Run Monthly Depreciation</p>
                        <p className="text-xs text-muted-foreground">
                            Idempotent — skips assets already posted for the period.
                        </p>
                    </div>
                    <div>
                        <label className="block text-xs uppercase tracking-wide text-muted-foreground">
                            Year
                        </label>
                        <input
                            type="number"
                            min={2000}
                            max={2099}
                            value={deprYear}
                            onChange={(e) => setDeprYear(Number(e.target.value))}
                            className="mt-1 w-24 rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </div>
                    <div>
                        <label className="block text-xs uppercase tracking-wide text-muted-foreground">
                            Month
                        </label>
                        <select
                            value={deprMonth}
                            onChange={(e) => setDeprMonth(Number(e.target.value))}
                            className="mt-1 rounded-md border bg-background px-3 py-2 text-sm"
                        >
                            {Array.from({ length: 12 }, (_, i) => i + 1).map((m) => (
                                <option key={m} value={m}>
                                    {new Date(2000, m - 1).toLocaleString('en-PH', { month: 'long' })}
                                </option>
                            ))}
                        </select>
                    </div>
                    <button
                        type="button"
                        onClick={() => compute.mutate({ year: deprYear, month: deprMonth })}
                        disabled={compute.isPending}
                        className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                    >
                        {compute.isPending ? 'Computing…' : 'Compute'}
                    </button>

                    {compute.isSuccess && compute.data && (
                        <span className="text-sm text-emerald-700">
                            {compute.data.assets_processed} asset(s) processed —{' '}
                            {formatPhp(compute.data.total_depreciation)} total.
                        </span>
                    )}

                    {compute.isError && (
                        <span className="text-sm text-destructive">
                            {(compute.error as { response?: { data?: { message?: string } } })
                                ?.response?.data?.message ?? 'Computation failed.'}
                        </span>
                    )}
                </div>
            )}

            <div className="mt-6">
                <DataTable
                    columns={columns}
                    rows={assets}
                    rowKey={(r) => r.id}
                    isLoading={isLoading}
                    emptyState="No fixed assets registered yet. Click 'Register Asset' to add one."
                />
            </div>
        </div>
    );
}

function AssetStatusBadge({ status }: { status: FixedAsset['status'] }) {
    const styles: Record<FixedAsset['status'], string> = {
        active:            'bg-emerald-100 text-emerald-800',
        disposed:          'bg-red-100 text-red-800',
        fully_depreciated: 'bg-slate-100 text-slate-700',
    };
    const labels: Record<FixedAsset['status'], string> = {
        active:            'Active',
        disposed:          'Disposed',
        fully_depreciated: 'Fully Depreciated',
    };
    return (
        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${styles[status]}`}>
            {labels[status]}
        </span>
    );
}

function CategoryBadge({ category, label }: { category: string; label: string }) {
    const palette: Record<string, string> = {
        land:                  'bg-green-100 text-green-800',
        building:              'bg-blue-100 text-blue-800',
        equipment:             'bg-orange-100 text-orange-800',
        vehicle:               'bg-cyan-100 text-cyan-800',
        furniture:             'bg-purple-100 text-purple-800',
        it_equipment:          'bg-violet-100 text-violet-800',
        leasehold_improvement: 'bg-amber-100 text-amber-800',
    };
    const cls = palette[category] ?? 'bg-slate-100 text-slate-700';
    return (
        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${cls}`}>{label}</span>
    );
}
