import { useState } from 'react';

import { DataTable, type Column } from '@/shared/components/DataTable';
import { formatPhp } from '@/shared/lib/money';

import { useSalesInvoices, type SalesInvoice } from '../api/sales-invoices';

export function SalesInvoicesPage() {
    const [status, setStatus] = useState<'' | 'draft' | 'posted' | 'voided'>('');
    const [from, setFrom] = useState<string>('');
    const [to, setTo] = useState<string>('');

    const { data: invoices, isLoading } = useSalesInvoices({
        status: status === '' ? undefined : status,
        from: from || undefined,
        to: to || undefined,
    });

    const columns: Column<SalesInvoice>[] = [
        {
            key: 'doc_no',
            header: 'Doc No',
            render: (i) => (
                <a href={`#/sales/invoices/${i.id}`} className="font-mono text-primary hover:underline">
                    {i.doc_no}
                </a>
            ),
        },
        { key: 'invoice_date', header: 'Date', numeric: true },
        {
            key: 'customer',
            header: 'Customer',
            render: (i) => i.customer_name ?? <span className="text-muted-foreground">{i.customer_id.slice(0, 8)}…</span>,
        },
        {
            key: 'kind',
            header: 'Kind',
            render: (i) => <span className="capitalize text-muted-foreground">{i.doc_kind}</span>,
        },
        {
            key: 'vatable',
            header: 'Vatable Sales',
            align: 'right',
            numeric: true,
            render: (i) => formatPhp(i.vatable_sales),
        },
        { key: 'vat', header: 'VAT', align: 'right', numeric: true, render: (i) => formatPhp(i.vat_amount) },
        { key: 'total', header: 'Total', align: 'right', numeric: true, render: (i) => formatPhp(i.total) },
        { key: 'status', header: 'Status', render: (i) => <InvoiceStatusBadge invoice={i} /> },
    ];

    return (
        <div className="container py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">Sales Invoices</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        BIR-numbered SI series. Posted entries are immutable — use Void to reverse.
                        Cash invoices get an Official Receipt at payment time; charge invoices accrue AR.
                    </p>
                </div>

                <div className="flex items-center gap-2 text-sm">
                    <input
                        type="date"
                        value={from}
                        onChange={(e) => setFrom(e.target.value)}
                        className="rounded-md border bg-background px-2 py-1"
                        title="Invoice date from"
                    />
                    <input
                        type="date"
                        value={to}
                        onChange={(e) => setTo(e.target.value)}
                        className="rounded-md border bg-background px-2 py-1"
                        title="Invoice date to"
                    />
                    <select
                        value={status}
                        onChange={(e) => setStatus(e.target.value as typeof status)}
                        className="rounded-md border bg-background px-2 py-1"
                    >
                        <option value="">All statuses</option>
                        <option value="draft">Draft</option>
                        <option value="posted">Posted</option>
                        <option value="voided">Voided</option>
                    </select>
                    <a
                        href="#/sales/invoices/new"
                        className="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:opacity-90"
                    >
                        + Issue SI
                    </a>
                </div>
            </header>

            <div className="mt-6">
                <DataTable
                    columns={columns}
                    rows={invoices}
                    rowKey={(i) => i.id}
                    isLoading={isLoading}
                    emptyState="No invoices match the current filters."
                />
            </div>
        </div>
    );
}

function InvoiceStatusBadge({ invoice }: { invoice: SalesInvoice }) {
    if (invoice.voided_at) {
        return (
            <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                Voided
            </span>
        );
    }
    if (invoice.posted_at) {
        return (
            <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">
                Posted
            </span>
        );
    }
    return (
        <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
            Draft
        </span>
    );
}
