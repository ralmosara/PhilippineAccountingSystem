import { useState } from 'react';

import { DataTable, type Column } from '@/shared/components/DataTable';
import { formatPhp } from '@/shared/lib/money';

import { useVendorBills, type VendorBill } from '../api/vendor-bills';

export function VendorBillsPage() {
    const [status, setStatus] = useState<string>('');
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');

    const { data: bills, isLoading } = useVendorBills({
        status: status || undefined,
        from: from || undefined,
        to: to || undefined,
    });

    const columns: Column<VendorBill>[] = [
        {
            key: 'vendor_invoice_no',
            header: 'Vendor Inv #',
            render: (b) => (
                <a
                    href={`#/procurement/bills/${b.id}`}
                    className="font-mono text-primary hover:underline"
                >
                    {b.vendor_invoice_no}
                </a>
            ),
        },
        { key: 'bill_date', header: 'Bill Date', numeric: true },
        {
            key: 'vendor',
            header: 'Vendor',
            render: (b) => b.vendor_name ?? <span className="text-muted-foreground">{b.vendor_id.slice(0, 8)}…</span>,
        },
        { key: 'subtotal', header: 'Subtotal', align: 'right', numeric: true, render: (b) => formatPhp(b.subtotal) },
        {
            key: 'vat_input',
            header: 'VAT Input',
            align: 'right',
            numeric: true,
            render: (b) => formatPhp(b.vat_input),
        },
        {
            key: 'wht',
            header: 'WHT',
            align: 'right',
            numeric: true,
            render: (b) => formatPhp(b.withholding_amount),
        },
        { key: 'total', header: 'Total', align: 'right', numeric: true, render: (b) => formatPhp(b.total) },
        {
            key: 'status',
            header: 'Status',
            render: (b) => <BillStatusBadge status={b.status} />,
        },
    ];

    return (
        <div className="container py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">Vendor Bills</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        AP side of the ledger. Posting a bill auto-computes VAT input + creditable
                        withholding (per ATC), books the JV, and issues a Form 2307 to the vendor.
                    </p>
                </div>

                <div className="flex items-center gap-2 text-sm">
                    <input
                        type="date"
                        value={from}
                        onChange={(e) => setFrom(e.target.value)}
                        className="rounded-md border bg-background px-2 py-1"
                    />
                    <input
                        type="date"
                        value={to}
                        onChange={(e) => setTo(e.target.value)}
                        className="rounded-md border bg-background px-2 py-1"
                    />
                    <select
                        value={status}
                        onChange={(e) => setStatus(e.target.value)}
                        className="rounded-md border bg-background px-2 py-1"
                    >
                        <option value="">All statuses</option>
                        <option value="draft">Draft</option>
                        <option value="posted">Posted</option>
                        <option value="paid">Paid</option>
                        <option value="voided">Voided</option>
                    </select>
                    <a
                        href="#/procurement/bills/new"
                        className="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:opacity-90"
                    >
                        + Post bill
                    </a>
                </div>
            </header>

            <div className="mt-6">
                <DataTable
                    columns={columns}
                    rows={bills}
                    rowKey={(b) => b.id}
                    isLoading={isLoading}
                    emptyState="No vendor bills match the current filters."
                />
            </div>
        </div>
    );
}

function BillStatusBadge({ status }: { status: VendorBill['status'] }) {
    const styles = {
        draft:  'bg-slate-100 text-slate-700',
        posted: 'bg-blue-100 text-blue-800',
        paid:   'bg-emerald-100 text-emerald-800',
        voided: 'bg-amber-100 text-amber-800',
    } as const;
    return (
        <span className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${styles[status]}`}>
            {status}
        </span>
    );
}
