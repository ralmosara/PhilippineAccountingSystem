import { useState } from 'react';

import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';
import { formatPhp } from '@/shared/lib/money';

import { useGenerateInventoryList } from '../api/inventory-list';

/**
 * Annual Inventory List (BIR submission, RMC 57-2015).
 *
 * Filed within 30 days after fiscal year-end. The generator action snapshots
 * every inventoriable item with on-hand qty + total value AS OF the chosen
 * date (year-end), produces a CSV in BIR's expected layout, and stores it
 * to MinIO. Operator downloads the CSV and uploads to the BIR eFPS portal.
 */
export function InventoryListPage() {
    const user = useAuthStore((s) => s.user);

    const today = new Date();
    // Default to last full year-end (Dec 31 of last year) since this is an
    // annual submission filed shortly after year-end.
    const defaultAsOf = `${today.getFullYear() - 1}-12-31`;
    const [asOf, setAsOf] = useState(defaultAsOf);

    const gen = useGenerateInventoryList();
    const canFile = hasPermission(user, 'inventory.list.generate');

    return (
        <div className="container py-8">
            <header>
                <h1 className="text-2xl font-semibold">Annual Inventory List</h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    RMC 57-2015 — required BIR submission within 30 days after fiscal year-end.
                    Snapshots every inventoriable item's on-hand quantity + moving-average value
                    AS OF the chosen date.
                </p>
            </header>

            <section className="mt-6 rounded-lg border bg-card p-5">
                <div className="flex items-end gap-3">
                    <div>
                        <label className="block text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            As-of date
                        </label>
                        <input
                            type="date"
                            value={asOf}
                            onChange={(e) => setAsOf(e.target.value)}
                            className="mt-1 rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </div>
                    <button
                        type="button"
                        onClick={() => gen.mutate({ as_of: asOf })}
                        disabled={gen.isPending || !canFile}
                        className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                    >
                        {gen.isPending ? 'Generating…' : 'Generate Inventory List'}
                    </button>
                </div>

                {!canFile && (
                    <p className="mt-3 text-xs text-muted-foreground">
                        You don't have <code>inventory.list.generate</code> permission. Ask an admin.
                    </p>
                )}

                {gen.isError && (
                    <div className="mt-4 rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                        {(gen.error as { response?: { data?: { message?: string } } })?.response?.data
                            ?.message ?? 'Generation failed.'}
                    </div>
                )}
            </section>

            {gen.data && (
                <section className="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 p-5">
                    <h2 className="text-base font-medium text-emerald-900">
                        ✓ Inventory List generated
                    </h2>
                    <dl className="mt-3 grid grid-cols-3 gap-3 text-sm">
                        <Stat label="As of"      value={gen.data.as_of} mono />
                        <Stat label="Items"      value={gen.data.line_count.toLocaleString()} />
                        <Stat label="Total Qty"  value={Number(gen.data.total_quantity).toLocaleString('en-PH', { maximumFractionDigits: 4 })} />
                        <Stat label="Total Value" value={formatPhp(gen.data.total_value)} colSpan={3} />
                    </dl>
                    <a
                        href={`/api/v1/storage/${gen.data.storage_path}`}
                        target="_blank"
                        rel="noreferrer"
                        className="mt-4 inline-block rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                    >
                        Download CSV
                    </a>
                </section>
            )}

            <p className="mt-6 text-xs text-muted-foreground">
                After download, upload the CSV via the BIR eFPS portal under
                "Inventory List (Annual)" before the 30-day deadline.
            </p>
        </div>
    );
}

function Stat({
    label,
    value,
    mono,
    colSpan,
}: {
    label: string;
    value: string;
    mono?: boolean;
    colSpan?: number;
}) {
    return (
        <div className={colSpan === 3 ? 'col-span-3' : ''}>
            <dt className="text-xs uppercase tracking-wide text-emerald-700">{label}</dt>
            <dd className={`mt-1 font-semibold tabular-nums text-emerald-900 ${mono ? 'font-mono' : ''}`}>
                {value}
            </dd>
        </div>
    );
}
