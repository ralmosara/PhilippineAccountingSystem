import { useState } from 'react';

import { REGIME_LABELS, useOsdElections, type OsdElection } from '../api/osd-elections';
import { SupersedeOsdElectionDialog } from '../components/SupersedeOsdElectionDialog';

/**
 * Tax → OSD Elections page.
 * One row per (fiscal_year, taxpayer_type) — operator sees which regime is
 * locked for the year, who locked it, and on which form. Click "Amend" to
 * supersede a row with a BIR-approved regime change.
 */
export function OsdElectionsPage() {
    const [yearFilter, setYearFilter] = useState<number>(new Date().getFullYear());
    const [activeOnly, setActiveOnly] = useState(true);
    const [amending, setAmending] = useState<OsdElection | null>(null);

    const { data: elections, isLoading } = useOsdElections({
        fiscal_year: yearFilter,
        active_only: activeOnly,
    });

    return (
        <div className="container py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">OSD Elections</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Locked deduction-regime elections per fiscal year. Established by the first
                        quarterly ITR; amendments require BIR approval.
                    </p>
                </div>

                <div className="flex items-center gap-3 text-sm">
                    <label>
                        Year{' '}
                        <input
                            type="number"
                            value={yearFilter}
                            onChange={(e) => setYearFilter(Number(e.target.value))}
                            className="w-24 rounded-md border bg-background px-2 py-1"
                        />
                    </label>
                    <label className="flex items-center gap-1">
                        <input
                            type="checkbox"
                            checked={activeOnly}
                            onChange={(e) => setActiveOnly(e.target.checked)}
                        />
                        Active only
                    </label>
                </div>
            </header>

            <div className="mt-6 overflow-hidden rounded-lg border bg-card">
                <table className="w-full text-sm">
                    <thead className="bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th className="px-4 py-2">Year</th>
                            <th className="px-4 py-2">Taxpayer</th>
                            <th className="px-4 py-2">Regime</th>
                            <th className="px-4 py-2">Declared in</th>
                            <th className="px-4 py-2">Locked</th>
                            <th className="px-4 py-2">Status</th>
                            <th className="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {isLoading && (
                            <tr>
                                <td colSpan={7} className="px-4 py-6 text-center text-muted-foreground">
                                    Loading…
                                </td>
                            </tr>
                        )}
                        {!isLoading && elections?.length === 0 && (
                            <tr>
                                <td colSpan={7} className="px-4 py-6 text-center text-muted-foreground">
                                    No elections found for {yearFilter}. The first quarterly ITR generated for
                                    this year will create one.
                                </td>
                            </tr>
                        )}
                        {elections?.map((e) => (
                            <tr key={e.id} className="border-t">
                                <td className="px-4 py-2 tabular-nums">{e.fiscal_year}</td>
                                <td className="px-4 py-2 capitalize">{e.taxpayer_type}</td>
                                <td className="px-4 py-2">
                                    <RegimeBadge regime={e.regime} />
                                </td>
                                <td className="px-4 py-2 text-xs text-muted-foreground">
                                    {e.declared_in.form_type}
                                    {e.declared_in.quarter ? ` Q${e.declared_in.quarter}` : ''}
                                </td>
                                <td className="px-4 py-2 text-xs text-muted-foreground">
                                    {new Date(e.locked_at).toLocaleDateString()}
                                </td>
                                <td className="px-4 py-2">
                                    {e.is_active ? (
                                        <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">
                                            Active
                                        </span>
                                    ) : (
                                        <span className="rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700">
                                            Superseded
                                        </span>
                                    )}
                                </td>
                                <td className="px-4 py-2 text-right">
                                    {e.is_active && (
                                        <button
                                            type="button"
                                            onClick={() => setAmending(e)}
                                            className="rounded-md border px-2 py-1 text-xs hover:bg-accent"
                                        >
                                            Amend
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {amending && (
                <SupersedeOsdElectionDialog
                    election={amending}
                    onClose={() => setAmending(null)}
                />
            )}
        </div>
    );
}

function RegimeBadge({ regime }: { regime: OsdElection['regime'] }) {
    const color =
        regime === 'osd'
            ? 'bg-yellow-50 text-yellow-800 border-yellow-200'
            : regime === 'flat_8pct'
              ? 'bg-purple-50 text-purple-800 border-purple-200'
              : 'bg-slate-50 text-slate-700 border-slate-200';

    return (
        <span className={`rounded-md border px-2 py-0.5 text-xs font-medium ${color}`}>
            {REGIME_LABELS[regime]}
        </span>
    );
}
