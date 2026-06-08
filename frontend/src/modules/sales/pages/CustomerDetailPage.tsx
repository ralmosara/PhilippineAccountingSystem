import { useCustomer, useCustomerArAging } from '../api/customers';
import { useOfficialReceipts } from '../api/official-receipts';
import { useSalesInvoices, type SalesInvoice } from '../api/sales-invoices';

import { DataTable, type Column } from '@/shared/components/DataTable';
import { formatPhp } from '@/shared/lib/money';

/**
 * Customer detail page — drilldown showing:
 *
 *   - Customer profile (class flags + payment terms)
 *   - AR aging buckets (Current / 1-30 / 31-60 / 61-90 / 91+) — server-authoritative
 *   - Recent invoices
 *   - Recent ORs (collections)
 */
export function CustomerDetailPage({ customerId }: { customerId: string }) {
    const { data: customer, isLoading: customerLoading } = useCustomer(customerId);
    const { data: invoices, isLoading: invoicesLoading } = useSalesInvoices({ customer_id: customerId });
    const { data: receipts } = useOfficialReceipts({ customer_id: customerId });
    const { data: aging } = useCustomerArAging(customerId);

    if (customerLoading || !customer) {
        return <div className="container py-8 text-sm text-muted-foreground">Loading…</div>;
    }

    return (
        <div className="container py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">{customer.registered_name}</h1>
                    <p className="mt-1 text-xs text-muted-foreground">
                        <span className="font-mono">{customer.customer_no}</span>
                        {customer.tin && <> · TIN {customer.tin}</>}
                        {customer.payment_terms_days > 0 && <> · Net {customer.payment_terms_days} days</>}
                    </p>
                    <div className="mt-2 flex gap-1">
                        {customer.is_government && <Chip tone="purple">Government</Chip>}
                        {customer.is_senior_citizen && <Chip tone="amber">Senior</Chip>}
                        {customer.is_pwd && <Chip tone="amber">PWD</Chip>}
                        {customer.is_vat_registered && <Chip tone="slate">VAT-registered</Chip>}
                    </div>
                </div>
                <div className="flex gap-2 text-sm">
                    <a href="#/sales/customers" className="rounded-md border px-3 py-1.5 hover:bg-accent">
                        ← Back to list
                    </a>
                    <a
                        href={`#/sales/customers/${customer.id}/edit`}
                        className="rounded-md border px-3 py-1.5 hover:bg-accent"
                    >
                        Edit
                    </a>
                    <a
                        href={`#/sales/invoices/new?customer_id=${customer.id}`}
                        className="rounded-md bg-primary px-3 py-1.5 font-medium text-primary-foreground hover:opacity-90"
                    >
                        + New invoice
                    </a>
                </div>
            </header>

            {/* ── AR Aging (server-authoritative) ─────────────────────── */}
            <section className="mt-6">
                <h2 className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    AR Aging {aging ? <>(as of {aging.as_of})</> : <>(loading…)</>}
                </h2>
                {aging && (
                    <>
                        <div className="mt-2 grid grid-cols-2 gap-3 md:grid-cols-5">
                            <AgingTile label="Current"   value={aging.buckets.current}  accent="emerald" />
                            <AgingTile label="1–30 days" value={aging.buckets.d1_30} />
                            <AgingTile label="31–60 days" value={aging.buckets.d31_60} accent={Number(aging.buckets.d31_60) > 0 ? 'amber' : undefined} />
                            <AgingTile label="61–90 days" value={aging.buckets.d61_90} accent={Number(aging.buckets.d61_90) > 0 ? 'orange' : undefined} />
                            <AgingTile label="91+ days"   value={aging.buckets.d91_plus} accent={Number(aging.buckets.d91_plus) > 0 ? 'destructive' : undefined} />
                        </div>
                        <div className="mt-2 inline-block rounded-md border bg-card px-3 py-1 text-xs">
                            <span className="text-muted-foreground">Total AR: </span>
                            <span className="font-semibold tabular-nums">{formatPhp(aging.total)}</span>
                            <span className="ml-2 text-muted-foreground">
                                across {aging.unpaid_invoice_count} unpaid invoice{aging.unpaid_invoice_count === 1 ? '' : 's'}
                            </span>
                        </div>
                    </>
                )}
            </section>

            {/* ── Invoices ────────────────────────────────────────────── */}
            <section className="mt-6">
                <h2 className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    Sales Invoices
                </h2>
                <div className="mt-2">
                    <DataTable
                        columns={invoiceColumns}
                        rows={invoices?.slice(0, 25)}
                        rowKey={(i) => i.id}
                        isLoading={invoicesLoading}
                        emptyState="No invoices yet for this customer."
                    />
                </div>
            </section>

            {/* ── Recent ORs ──────────────────────────────────────────── */}
            {receipts && receipts.length > 0 && (
                <section className="mt-6">
                    <h2 className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        Recent Official Receipts
                    </h2>
                    <div className="mt-2 overflow-hidden rounded-lg border bg-card">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-2">OR No</th>
                                    <th className="px-4 py-2">Date</th>
                                    <th className="px-4 py-2">Method</th>
                                    <th className="px-4 py-2 text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                {receipts.slice(0, 10).map((r) => (
                                    <tr key={r.id} className="border-t">
                                        <td className="px-4 py-1.5 font-mono">{r.doc_no}</td>
                                        <td className="px-4 py-1.5">{r.received_date}</td>
                                        <td className="px-4 py-1.5 capitalize text-muted-foreground">
                                            {r.payment_method.replace('_', ' ')}
                                        </td>
                                        <td className="px-4 py-1.5 text-right tabular-nums">
                                            {formatPhp(r.amount)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}
        </div>
    );
}

const invoiceColumns: Column<SalesInvoice>[] = [
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
    { key: 'due_date', header: 'Due', render: (i) => i.due_date ?? '—' },
    { key: 'total', header: 'Total', align: 'right', numeric: true, render: (i) => formatPhp(i.total) },
    {
        key: 'status',
        header: 'Status',
        render: (i) =>
            i.voided_at ? <span className="text-amber-700">Voided</span>
            : i.posted_at ? <span className="text-emerald-700">Posted</span>
            : <span className="text-slate-700">Draft</span>,
    },
];

function AgingTile({
    label,
    value,
    accent,
}: {
    label: string;
    value: string;
    accent?: 'emerald' | 'amber' | 'orange' | 'destructive' | undefined;
}) {
    const colour =
        accent === 'emerald' ? 'border-emerald-200 bg-emerald-50 text-emerald-900'
        : accent === 'amber' ? 'border-amber-200 bg-amber-50 text-amber-900'
        : accent === 'orange' ? 'border-orange-200 bg-orange-50 text-orange-900'
        : accent === 'destructive' ? 'border-destructive/30 bg-destructive/5 text-destructive'
        : 'bg-card';
    return (
        <div className={`rounded-lg border p-3 ${colour}`}>
            <div className="text-xs uppercase tracking-wide opacity-70">{label}</div>
            <div className="mt-1 text-lg font-semibold tabular-nums">
                {formatPhp(value)}
            </div>
        </div>
    );
}

function Chip({ children, tone }: { children: React.ReactNode; tone: 'purple' | 'amber' | 'slate' }) {
    const c = tone === 'purple' ? 'bg-purple-100 text-purple-800'
        : tone === 'amber' ? 'bg-amber-100 text-amber-800'
        : 'bg-slate-100 text-slate-700';
    return <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${c}`}>{children}</span>;
}
