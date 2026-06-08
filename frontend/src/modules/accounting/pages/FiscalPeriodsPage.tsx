import { useState } from 'react';

import { DataTable, type Column } from '@/shared/components/DataTable';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

import { useFiscalPeriods, useLockFiscalPeriod, type FiscalPeriod } from '../api/fiscal-periods';

/**
 * Fiscal Period management.
 *
 * BIR's CAS rule: once a period is locked, NO journal entry may be posted,
 * updated, or reversed within its date range. The lock is enforced by a
 * Postgres trigger (see migration); this UI is the human surface for the
 * monthly close ritual.
 *
 * Locking is MFA-gated and irreversible from the UI. If a genuine correction
 * is needed (rare; usually only when BIR audit finding requires it), an
 * Approver requests a per-period unlock that creates an audit event.
 */
export function FiscalPeriodsPage() {
    const user = useAuthStore((s) => s.user);
    const [year, setYear] = useState<number>(new Date().getFullYear());
    const [confirming, setConfirming] = useState<FiscalPeriod | null>(null);

    const { data: periods, isLoading } = useFiscalPeriods({ year });
    const lock = useLockFiscalPeriod();
    const canLock = hasPermission(user, 'accounting.periods.lock');

    const columns: Column<FiscalPeriod>[] = [
        {
            key: 'period',
            header: 'Period',
            render: (p) => `${p.period_number.toString().padStart(2, '0')} · ${p.starts_on} – ${p.ends_on}`,
        },
        { key: 'year_label', header: 'Fiscal Year', render: (p) => p.fiscal_year_label ?? '—' },
        {
            key: 'status',
            header: 'Status',
            render: (p) =>
                p.locked_at ? (
                    <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                        🔒 Locked
                    </span>
                ) : (
                    <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">
                        Open
                    </span>
                ),
        },
        {
            key: 'locked_at',
            header: 'Locked at',
            render: (p) =>
                p.locked_at ? (
                    <span className="text-xs text-muted-foreground">
                        {new Date(p.locked_at).toLocaleDateString()}
                    </span>
                ) : (
                    '—'
                ),
        },
        {
            key: 'action',
            header: '',
            render: (p) =>
                !p.locked_at && canLock ? (
                    <button
                        type="button"
                        onClick={() => setConfirming(p)}
                        className="rounded-md border px-2 py-0.5 text-xs hover:bg-accent"
                    >
                        Lock period
                    </button>
                ) : null,
        },
    ];

    return (
        <div className="container py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">Fiscal Periods</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Month-end close. Locking a period is enforced at the DB level —
                        no journal entry may be posted or reversed within its date range after lock.
                    </p>
                </div>

                <div className="flex items-center gap-2 text-sm">
                    <label>
                        Year{' '}
                        <input
                            type="number"
                            value={year}
                            onChange={(e) => setYear(Number(e.target.value))}
                            className="w-24 rounded-md border bg-background px-2 py-1"
                        />
                    </label>
                </div>
            </header>

            <div className="mt-6">
                <DataTable
                    columns={columns}
                    rows={periods}
                    rowKey={(p) => p.id}
                    isLoading={isLoading}
                    emptyState={`No fiscal periods configured for ${year}. Run the fiscal-year setup wizard first.`}
                />
            </div>

            {confirming && (
                <LockConfirmDialog
                    period={confirming}
                    isPending={lock.isPending}
                    error={lock.error}
                    onClose={() => setConfirming(null)}
                    onConfirm={() =>
                        lock.mutate(
                            { id: confirming.id },
                            { onSuccess: () => setConfirming(null) },
                        )
                    }
                />
            )}
        </div>
    );
}

function LockConfirmDialog({
    period,
    isPending,
    error,
    onClose,
    onConfirm,
}: {
    period: FiscalPeriod;
    isPending: boolean;
    error: unknown;
    onClose: () => void;
    onConfirm: () => void;
}) {
    const [confirmText, setConfirmText] = useState('');
    const required = 'LOCK';

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            onClick={onClose}
        >
            <div
                onClick={(e) => e.stopPropagation()}
                className="w-full max-w-md space-y-4 rounded-lg bg-card p-6 shadow-lg"
            >
                <header>
                    <h2 className="text-lg font-semibold">
                        Lock period {period.period_number.toString().padStart(2, '0')}?
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Locking period <strong>{period.starts_on} – {period.ends_on}</strong>{' '}
                        permanently prevents any new journal entry from being posted or any existing
                        entry from being reversed within these dates. This is the BIR-required
                        month-end close.
                    </p>
                </header>

                <div className="rounded-md border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900">
                    <strong>This action is enforced at the database level.</strong> Unlocking later
                    requires a senior approver and creates a permanent audit event. Verify all
                    expected entries are posted before proceeding.
                </div>

                <div className="space-y-2">
                    <label className="text-sm">
                        Type <code className="rounded bg-muted px-1 font-mono">{required}</code> to confirm:
                    </label>
                    <input
                        type="text"
                        value={confirmText}
                        onChange={(e) => setConfirmText(e.target.value)}
                        className="w-full rounded-md border bg-background px-3 py-2 font-mono text-sm"
                        autoFocus
                    />
                </div>

                {error !== null && error !== undefined && (
                    <div className="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-xs text-destructive">
                        {(error as { response?: { data?: { message?: string } } })?.response?.data
                            ?.message ?? 'Lock failed.'}
                    </div>
                )}

                <div className="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-md border px-3 py-1.5 text-sm hover:bg-accent"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={onConfirm}
                        disabled={confirmText !== required || isPending}
                        className="rounded-md bg-amber-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-50"
                    >
                        {isPending ? 'Locking…' : 'Lock period'}
                    </button>
                </div>
            </div>
        </div>
    );
}
