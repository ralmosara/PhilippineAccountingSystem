import { useState } from 'react';

import { DataTable, type Column } from '@/shared/components/DataTable';
import { formatPhp } from '@/shared/lib/money';
import { fromScaled, sumScaled } from '@/shared/lib/bcmath';

import { useJournalEntries, type JournalEntry } from '../api/journals';

export function JournalEntriesPage() {
    const [status, setStatus] = useState<'' | 'draft' | 'posted' | 'reversed'>('');

    const { data: entries, isLoading } = useJournalEntries({
        status: status === '' ? undefined : status,
    });

    const columns: Column<JournalEntry>[] = [
        {
            key: 'doc_no',
            header: 'Doc No',
            render: (e) => (
                <a href={`#/accounting/journals/${e.id}`} className="font-mono text-primary hover:underline">
                    {e.doc_no}
                </a>
            ),
        },
        { key: 'entry_date', header: 'Date', numeric: true },
        { key: 'memo', header: 'Memo', render: (e) => e.memo ?? '—' },
        {
            key: 'source',
            header: 'Source',
            render: (e) => <span className="capitalize text-muted-foreground">{e.source}</span>,
        },
        { key: 'lines', header: 'Lines', align: 'right', numeric: true, render: (e) => e.lines.length },
        {
            key: 'total',
            header: 'Debits = Credits',
            align: 'right',
            numeric: true,
            render: (e) => formatPhp(fromScaled(sumScaled(e.lines.map((l) => l.debit)), 2)),
        },
        { key: 'status', header: 'Status', render: (e) => <StatusBadge status={e.status} /> },
    ];

    return (
        <div className="container py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">Journal Entries</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        General journal — every posted entry feeds the GL, books of accounts, and
                        financial statements. Drafts can be edited; posted entries are immutable
                        (use Reverse to undo).
                    </p>
                </div>

                <div className="flex items-center gap-3 text-sm">
                    <select
                        value={status}
                        onChange={(e) => setStatus(e.target.value as typeof status)}
                        className="rounded-md border bg-background px-2 py-1"
                    >
                        <option value="">All statuses</option>
                        <option value="draft">Draft</option>
                        <option value="posted">Posted</option>
                        <option value="reversed">Reversed</option>
                    </select>

                    <a
                        href="#/accounting/journals/new"
                        className="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:opacity-90"
                    >
                        + New JV
                    </a>
                </div>
            </header>

            <div className="mt-6">
                <DataTable
                    columns={columns}
                    rows={entries}
                    rowKey={(e) => e.id}
                    isLoading={isLoading}
                    emptyState="No journal entries. Click '+ New JV' to create one."
                />
            </div>
        </div>
    );
}

function StatusBadge({ status }: { status: 'draft' | 'posted' | 'reversed' }) {
    const styles = {
        draft:    'bg-slate-100 text-slate-700',
        posted:   'bg-emerald-100 text-emerald-800',
        reversed: 'bg-amber-100 text-amber-800',
    } as const;
    return (
        <span className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${styles[status]}`}>
            {status}
        </span>
    );
}
