import { useState } from 'react';

import { DataTable, type Column } from '@/shared/components/DataTable';
import { formatPhp } from '@/shared/lib/money';

import {
    PAYMENT_METHOD_LABELS,
    useOfficialReceipts,
    type OfficialReceipt,
    type PaymentMethod,
} from '../api/official-receipts';

/**
 * Standalone OR list page — used by the cashier / AR clerk to scan receipts
 * issued in a period. ORs are issued from the SI detail page, not from here
 * (an OR without a matching SI is rare and currently not supported by the UI).
 */
export function OfficialReceiptsPage() {
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');
    const [methodFilter, setMethodFilter] = useState<PaymentMethod | ''>('');

    const { data, isLoading } = useOfficialReceipts({
        from: from || undefined,
        to: to || undefined,
    });

    const filtered =
        methodFilter === '' ? data : data?.filter((r) => r.payment_method === methodFilter);

    const totalReceipts = (filtered ?? []).reduce((sum, r) => sum + Number(r.amount), 0);

    const columns: Column<OfficialReceipt>[] = [
        {
            key: 'doc_no',
            header: 'OR No',
            render: (r) => <span className="font-mono">{r.doc_no}</span>,
        },
        { key: 'received_date', header: 'Date', numeric: true },
        {
            key: 'customer',
            header: 'Customer',
            render: (r) =>
                r.customer_name ?? (
                    <span className="text-muted-foreground">{r.customer_id.slice(0, 8)}…</span>
                ),
        },
        {
            key: 'sales_invoice',
            header: 'Against SI',
            render: (r) =>
                r.sales_invoice_id ? (
                    <a
                        href={`#/sales/invoices/${r.sales_invoice_id}`}
                        className="font-mono text-xs text-primary hover:underline"
                    >
                        {r.sales_invoice_doc_no ?? r.sales_invoice_id.slice(0, 8) + '…'}
                    </a>
                ) : (
                    <span className="text-xs text-muted-foreground">—</span>
                ),
        },
        {
            key: 'method',
            header: 'Method',
            render: (r) => PAYMENT_METHOD_LABELS[r.payment_method],
        },
        {
            key: 'reference',
            header: 'Reference',
            render: (r) => <span className="text-xs">{r.reference_no ?? '—'}</span>,
        },
        {
            key: 'amount',
            header: 'Amount',
            align: 'right',
            numeric: true,
            render: (r) => formatPhp(r.amount),
        },
    ];

    return (
        <div className="container py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">Official Receipts</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Cash / payment receipts issued against sales invoices. To issue a new OR,
                        open the source SI and click <em>Issue OR</em>.
                    </p>
                </div>

                <div className="flex items-center gap-2 text-sm">
                    <input
                        type="date"
                        value={from}
                        onChange={(e) => setFrom(e.target.value)}
                        className="rounded-md border bg-background px-2 py-1"
                        title="Received date from"
                    />
                    <input
                        type="date"
                        value={to}
                        onChange={(e) => setTo(e.target.value)}
                        className="rounded-md border bg-background px-2 py-1"
                        title="Received date to"
                    />
                    <select
                        value={methodFilter}
                        onChange={(e) => setMethodFilter(e.target.value as PaymentMethod | '')}
                        className="rounded-md border bg-background px-2 py-1"
                    >
                        <option value="">All payment methods</option>
                        {(Object.keys(PAYMENT_METHOD_LABELS) as PaymentMethod[]).map((m) => (
                            <option key={m} value={m}>
                                {PAYMENT_METHOD_LABELS[m]}
                            </option>
                        ))}
                    </select>
                </div>
            </header>

            {filtered && filtered.length > 0 && (
                <div className="mt-4 inline-block rounded-md border bg-card px-4 py-2 text-sm">
                    <span className="text-xs uppercase tracking-wide text-muted-foreground">
                        Total received
                    </span>
                    <span className="ml-3 font-semibold tabular-nums">
                        {formatPhp(totalReceipts.toString())}
                    </span>
                    <span className="ml-3 text-xs text-muted-foreground">
                        across {filtered.length} OR{filtered.length === 1 ? '' : 's'}
                    </span>
                </div>
            )}

            <div className="mt-6">
                <DataTable
                    columns={columns}
                    rows={filtered}
                    rowKey={(r) => r.id}
                    isLoading={isLoading}
                    emptyState="No official receipts in the selected range."
                />
            </div>
        </div>
    );
}
