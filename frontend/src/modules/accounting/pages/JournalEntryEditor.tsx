import { useCallback, useEffect, useMemo, useState } from 'react';

import { AccountPicker } from '@/shared/components/AccountPicker';
import { MoneyInput } from '@/shared/components/MoneyInput';
import { formatPhp } from '@/shared/lib/money';
import { equalsScaled, fromScaled, sumScaled } from '@/shared/lib/bcmath';

import {
    usePostJournalEntry,
    useJournalEntry,
    useSaveJournalEntry,
    type JournalLineInput,
} from '../api/journals';

/**
 * Journal Entry Editor
 * ─────────────────────────────────────────────────────────────────────────
 * Accountants live in this screen, so keyboard ergonomics dominate:
 *
 *   Tab          → next cell (loops through Account / Memo / Debit / Credit)
 *   Shift+Tab    → previous cell
 *   Enter        → if last cell of last row → add a new row & focus its Account
 *                 → otherwise behaves like Tab
 *   Ctrl/Cmd+S   → save as draft
 *   Alt+P        → post (after save) — only available if balanced
 *
 * The red banner under the header updates every keystroke to show whether
 * sum(debits) === sum(credits). Computed in BigInt-scaled arithmetic so a
 * 200-row JV with sub-centavo precision still gives a deterministic answer.
 */
interface Props {
    mode: 'new' | 'edit';
    journalId?: string;
}

interface LineRow {
    line_no: number;
    account_id: string;
    description: string;
    debit: string;
    credit: string;
}

const blankLine = (n: number): LineRow => ({
    line_no: n,
    account_id: '',
    description: '',
    debit: '',
    credit: '',
});

export function JournalEntryEditor({ mode, journalId }: Props) {
    const [entryDate, setEntryDate] = useState(new Date().toISOString().slice(0, 10));
    const [memo, setMemo] = useState('');
    const [lines, setLines] = useState<LineRow[]>([blankLine(1), blankLine(2)]);

    const { data: existing } = useJournalEntry(mode === 'edit' ? (journalId ?? null) : null);
    const save = useSaveJournalEntry();
    const post = usePostJournalEntry();

    // Rehydrate when editing
    useEffect(() => {
        if (existing) {
            setEntryDate(existing.entry_date);
            setMemo(existing.memo ?? '');
            setLines(
                existing.lines.map((l) => ({
                    line_no: l.line_no,
                    account_id: l.account_id,
                    description: l.description ?? '',
                    debit: l.debit,
                    credit: l.credit,
                })),
            );
        }
    }, [existing]);

    // ── Live double-entry validation ─────────────────────────────────────
    const validation = useMemo(() => {
        const debits = sumScaled(lines.map((l) => l.debit));
        const credits = sumScaled(lines.map((l) => l.credit));
        return {
            debits,
            credits,
            difference: debits - credits,
            isBalanced: equalsScaled(debits, credits) && debits !== 0n,
            isEmpty: debits === 0n && credits === 0n,
        };
    }, [lines]);

    // ── Row mutation helpers ─────────────────────────────────────────────
    const updateCell = useCallback(
        <K extends keyof LineRow>(rowIdx: number, field: K, value: LineRow[K]) => {
            setLines((prev) =>
                prev.map((row, i) => (i === rowIdx ? { ...row, [field]: value } : row)),
            );
        },
        [],
    );

    const addRow = useCallback(() => {
        setLines((prev) => [...prev, blankLine(prev.length + 1)]);
    }, []);

    const removeRow = useCallback((rowIdx: number) => {
        setLines((prev) =>
            prev.length <= 2
                ? prev
                : prev.filter((_, i) => i !== rowIdx).map((row, i) => ({ ...row, line_no: i + 1 })),
        );
    }, []);

    // ── Keyboard handlers ────────────────────────────────────────────────
    const handleSave = useCallback(
        (postAfter = false) => {
            const cleaned: JournalLineInput[] = lines
                .filter((l) => l.account_id && (l.debit !== '' || l.credit !== ''))
                .map((l) => ({
                    line_no: l.line_no,
                    account_id: l.account_id,
                    description: l.description,
                    debit: l.debit || '0',
                    credit: l.credit || '0',
                }));

            save.mutate(
                {
                    id: mode === 'edit' ? journalId : undefined,
                    entry_date: entryDate,
                    memo,
                    document_series_id: '',                // backend fills from active series
                    lines: cleaned,
                },
                {
                    onSuccess: (saved) => {
                        if (postAfter && validation.isBalanced) {
                            post.mutate(saved.id, {
                                onSuccess: () => (window.location.hash = '#/accounting/journals'),
                            });
                        }
                    },
                },
            );
        },
        [lines, entryDate, memo, mode, journalId, save, post, validation.isBalanced],
    );

    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                handleSave(false);
            } else if (e.altKey && (e.key === 'p' || e.key === 'P')) {
                e.preventDefault();
                handleSave(true);
            }
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, [handleSave]);

    return (
        <div className="container py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">
                        {mode === 'new' ? 'New Journal Entry' : `Edit ${existing?.doc_no ?? ''}`}
                    </h1>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Tab to next cell · Enter to add row · Ctrl+S to save · Alt+P to post (if balanced)
                    </p>
                </div>
                <div className="flex gap-2 text-sm">
                    <a
                        href="#/accounting/journals"
                        className="rounded-md border px-3 py-1.5 hover:bg-accent"
                    >
                        Cancel
                    </a>
                    <button
                        type="button"
                        onClick={() => handleSave(false)}
                        disabled={save.isPending}
                        className="rounded-md border px-3 py-1.5 hover:bg-accent disabled:opacity-50"
                    >
                        {save.isPending ? 'Saving…' : 'Save draft'}
                    </button>
                    <button
                        type="button"
                        onClick={() => handleSave(true)}
                        disabled={!validation.isBalanced || save.isPending}
                        className="rounded-md bg-primary px-3 py-1.5 font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                    >
                        Save & post
                    </button>
                </div>
            </header>

            {/* ── Balance banner ──────────────────────────────────────── */}
            <BalanceBanner v={validation} />

            {/* ── Header fields ───────────────────────────────────────── */}
            <div className="mt-4 grid grid-cols-2 gap-4">
                <div>
                    <label className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        Entry Date
                    </label>
                    <input
                        type="date"
                        value={entryDate}
                        onChange={(e) => setEntryDate(e.target.value)}
                        className="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm"
                    />
                </div>
                <div>
                    <label className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        Memo
                    </label>
                    <input
                        type="text"
                        value={memo}
                        onChange={(e) => setMemo(e.target.value)}
                        placeholder="e.g. Q1 accruals adjustment"
                        className="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm"
                    />
                </div>
            </div>

            {/* ── Lines table ─────────────────────────────────────────── */}
            <div className="mt-4 overflow-hidden rounded-lg border bg-card">
                <table className="w-full text-sm">
                    <thead className="bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th className="w-12 px-2 py-2 text-right">#</th>
                            <th className="px-2 py-2">Account</th>
                            <th className="px-2 py-2">Description</th>
                            <th className="w-40 px-2 py-2 text-right">Debit</th>
                            <th className="w-40 px-2 py-2 text-right">Credit</th>
                            <th className="w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {lines.map((row, i) => (
                            <tr key={i} className="border-t">
                                <td className="px-2 py-1 text-right text-muted-foreground tabular-nums">
                                    {row.line_no}
                                </td>
                                <td className="px-2 py-1">
                                    <AccountPicker
                                        value={row.account_id}
                                        onChange={(id) => updateCell(i, 'account_id', id)}
                                        className=""
                                    />
                                </td>
                                <td className="px-2 py-1">
                                    <input
                                        type="text"
                                        value={row.description}
                                        onChange={(e) => updateCell(i, 'description', e.target.value)}
                                        className="w-full rounded-md border bg-background px-2 py-1 text-xs"
                                    />
                                </td>
                                <td className="px-2 py-1">
                                    <MoneyInput
                                        value={row.debit}
                                        onChange={(v) => {
                                            updateCell(i, 'debit', v);
                                            if (v) updateCell(i, 'credit', '');
                                        }}
                                    />
                                </td>
                                <td className="px-2 py-1">
                                    <MoneyInput
                                        value={row.credit}
                                        onChange={(v) => {
                                            updateCell(i, 'credit', v);
                                            if (v) updateCell(i, 'debit', '');
                                        }}
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter' && i === lines.length - 1) {
                                                e.preventDefault();
                                                addRow();
                                            }
                                        }}
                                    />
                                </td>
                                <td className="px-2 py-1 text-right">
                                    <button
                                        type="button"
                                        onClick={() => removeRow(i)}
                                        disabled={lines.length <= 2}
                                        className="text-xs text-muted-foreground hover:text-destructive disabled:opacity-30"
                                        title="Remove row"
                                    >
                                        ✕
                                    </button>
                                </td>
                            </tr>
                        ))}
                        <tr className="border-t bg-muted/30 font-medium tabular-nums">
                            <td colSpan={3} className="px-2 py-2 text-right text-xs uppercase text-muted-foreground">
                                Totals
                            </td>
                            <td className="px-2 py-2 text-right">
                                {formatPhp(fromScaled(validation.debits, 2))}
                            </td>
                            <td className="px-2 py-2 text-right">
                                {formatPhp(fromScaled(validation.credits, 2))}
                            </td>
                            <td />
                        </tr>
                    </tbody>
                </table>
            </div>

            <button
                type="button"
                onClick={addRow}
                className="mt-3 rounded-md border border-dashed px-3 py-1.5 text-xs text-muted-foreground hover:bg-accent"
            >
                + Add row
            </button>

            {save.isError && (
                <div className="mt-4 rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                    {(save.error as { response?: { data?: { message?: string } } })?.response?.data
                        ?.message ?? 'Save failed.'}
                </div>
            )}
        </div>
    );
}

function BalanceBanner({
    v,
}: {
    v: { debits: bigint; credits: bigint; difference: bigint; isBalanced: boolean; isEmpty: boolean };
}) {
    if (v.isEmpty) {
        return (
            <div className="mt-4 rounded-md border bg-muted/30 px-3 py-2 text-sm text-muted-foreground">
                Enter debit and credit lines below. The banner turns green when totals match.
            </div>
        );
    }

    if (v.isBalanced) {
        return (
            <div className="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                ✓ Balanced — debits {formatPhp(fromScaled(v.debits, 2))} = credits{' '}
                {formatPhp(fromScaled(v.credits, 2))}. Ready to post.
            </div>
        );
    }

    const diff = v.difference < 0n ? -v.difference : v.difference;
    const side = v.difference < 0n ? 'credits' : 'debits';
    return (
        <div className="mt-4 rounded-md border border-destructive/40 bg-destructive/5 px-3 py-2 text-sm text-destructive">
            ✗ Unbalanced — {side} exceeds the other side by {formatPhp(fromScaled(diff, 2))}. Posting is
            blocked until the entry balances.
        </div>
    );
}

