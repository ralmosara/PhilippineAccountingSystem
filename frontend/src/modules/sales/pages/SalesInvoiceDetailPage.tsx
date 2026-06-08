import { useState } from 'react';

import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';
import { formatPhp } from '@/shared/lib/money';

import {
    PAYMENT_METHOD_LABELS,
    useOfficialReceipts,
} from '../api/official-receipts';
import {
    useRetransmitToEis,
    useSalesInvoice,
    useVoidSalesInvoice,
} from '../api/sales-invoices';
import { IssueOfficialReceiptDialog } from '../components/IssueOfficialReceiptDialog';

/**
 * View one sales invoice. Auditors and accountants land here after issuing
 * to confirm doc_no, posted JV, and EIS submission status. Provides:
 *
 *   - Void (MFA-gated) — books a reversal JV and soft-voids the SI
 *   - Re-transmit to EIS (auditor / ops use, MFA-gated) — useful when
 *     the original async submission failed or was rejected
 *
 * The journal entry link jumps to the JV editor at the posted entry.
 */
export function SalesInvoiceDetailPage({ invoiceId }: { invoiceId: string }) {
    const user = useAuthStore((s) => s.user);
    const { data: invoice, isLoading } = useSalesInvoice(invoiceId);
    const voidIt = useVoidSalesInvoice();
    const retransmit = useRetransmitToEis();
    const [voidOpen, setVoidOpen] = useState(false);
    const [orOpen, setOrOpen] = useState(false);

    const { data: receipts } = useOfficialReceipts({ sales_invoice_id: invoiceId });

    if (isLoading) {
        return (
            <div className="container py-8 text-sm text-muted-foreground">Loading invoice…</div>
        );
    }
    if (!invoice) {
        return <div className="container py-8 text-sm text-muted-foreground">Not found.</div>;
    }

    const canVoid = hasPermission(user, 'sales.invoices.void');
    const canRetransmit = hasPermission(user, 'tax.eis.transmit');
    const canIssueOr = hasPermission(user, 'sales.or.issue');

    const paidTotal = (receipts ?? []).reduce((sum, r) => sum + Number(r.amount), 0);
    const balance = Number(invoice.total) - paidTotal;
    const isFullyPaid = balance <= 0 && paidTotal > 0;

    return (
        <div className="container py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="font-mono text-2xl font-semibold">{invoice.doc_no}</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {invoice.invoice_date} · {invoice.doc_kind} ·{' '}
                        <Status invoice={invoice} />
                    </p>
                </div>
                <div className="flex gap-2 text-sm">
                    <a href="#/sales/invoices" className="rounded-md border px-3 py-1.5 hover:bg-accent">
                        ← Back to list
                    </a>
                    {!invoice.voided_at && invoice.posted_at && canIssueOr && !isFullyPaid && (
                        <button
                            type="button"
                            onClick={() => setOrOpen(true)}
                            className="rounded-md bg-primary px-3 py-1.5 font-medium text-primary-foreground hover:opacity-90"
                        >
                            Issue OR (payment)
                        </button>
                    )}
                    {!invoice.voided_at && invoice.posted_at && canRetransmit && (
                        <button
                            type="button"
                            onClick={() => retransmit.mutate(invoice.id)}
                            disabled={retransmit.isPending}
                            className="rounded-md border px-3 py-1.5 hover:bg-accent disabled:opacity-50"
                        >
                            {retransmit.isPending ? 'Queuing…' : 'Re-transmit to EIS'}
                        </button>
                    )}
                    {!invoice.voided_at && invoice.posted_at && canVoid && (
                        <button
                            type="button"
                            onClick={() => setVoidOpen(true)}
                            className="rounded-md border border-destructive/30 px-3 py-1.5 text-destructive hover:bg-destructive/10"
                        >
                            Void invoice
                        </button>
                    )}
                </div>
            </header>

            {retransmit.isSuccess && (
                <div className="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    EIS retransmission queued.
                </div>
            )}

            {/* ── Header summary ──────────────────────────────────────── */}
            <div className="mt-6 grid grid-cols-2 gap-4">
                <Section title="Customer / dates">
                    <KV label="Customer" value={invoice.customer_name ?? invoice.customer_id} />
                    <KV label="Doc kind" value={invoice.doc_kind} />
                    <KV label="Invoice date" value={invoice.invoice_date} />
                    <KV label="Due date" value={invoice.due_date ?? '—'} />
                    <KV label="Currency" value={`${invoice.currency} (FX ${invoice.fx_rate})`} />
                </Section>

                <Section title="Computed totals">
                    <KV label="Vatable sales" value={formatPhp(invoice.vatable_sales)} />
                    {Number(invoice.vat_zero_rated_sales) > 0 && (
                        <KV label="Zero-rated sales" value={formatPhp(invoice.vat_zero_rated_sales)} />
                    )}
                    {Number(invoice.vat_exempt_sales) > 0 && (
                        <KV label="Exempt sales" value={formatPhp(invoice.vat_exempt_sales)} />
                    )}
                    <KV label="Output VAT (12%)" value={formatPhp(invoice.vat_amount)} />
                    {Number(invoice.senior_pwd_discount) > 0 && (
                        <KV
                            label="Senior / PWD discount"
                            value={`(${formatPhp(invoice.senior_pwd_discount)})`}
                            muted
                        />
                    )}
                    {Number(invoice.withheld_vat) > 0 && (
                        <KV
                            label="Less: Withheld VAT"
                            value={`(${formatPhp(invoice.withheld_vat)})`}
                            muted
                        />
                    )}
                    <KV label="Total due" value={formatPhp(invoice.total)} bold />
                </Section>
            </div>

            {/* ── Lines ───────────────────────────────────────────────── */}
            <div className="mt-6 overflow-hidden rounded-lg border bg-card">
                <table className="w-full text-sm">
                    <thead className="bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th className="w-12 px-2 py-2 text-right">#</th>
                            <th className="px-2 py-2">Description</th>
                            <th className="w-24 px-2 py-2 text-right">Qty</th>
                            <th className="w-32 px-2 py-2 text-right">Unit Price</th>
                            <th className="w-28 px-2 py-2 text-right">VAT</th>
                            <th className="w-32 px-2 py-2 text-right">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        {invoice.lines.map((l) => (
                            <tr key={l.id ?? `${l.line_no}-${l.description}`} className="border-t">
                                <td className="px-2 py-1.5 text-right text-muted-foreground tabular-nums">
                                    {l.line_no ?? '—'}
                                </td>
                                <td className="px-2 py-1.5">{l.description}</td>
                                <td className="px-2 py-1.5 text-right tabular-nums">{l.quantity}</td>
                                <td className="px-2 py-1.5 text-right tabular-nums">
                                    {formatPhp(l.unit_price)}
                                </td>
                                <td className="px-2 py-1.5 text-right tabular-nums">
                                    {formatPhp(l.vat_amount ?? '0')}
                                </td>
                                <td className="px-2 py-1.5 text-right tabular-nums font-medium">
                                    {formatPhp(l.line_total ?? '0')}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* ── Payments / Official Receipts ────────────────────────── */}
            {(receipts?.length ?? 0) > 0 && (
                <div className="mt-6 overflow-hidden rounded-lg border bg-card">
                    <div className="flex items-center justify-between border-b bg-muted/30 px-4 py-2">
                        <strong className="text-sm">Payments received</strong>
                        <span className="text-xs text-muted-foreground tabular-nums">
                            Paid {formatPhp(paidTotal.toString())} of {formatPhp(invoice.total)}
                            {balance > 0 && <> · Balance {formatPhp(balance.toString())}</>}
                            {isFullyPaid && (
                                <span className="ml-2 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-medium text-emerald-800">
                                    Fully paid
                                </span>
                            )}
                        </span>
                    </div>
                    <table className="w-full text-sm">
                        <thead className="bg-muted/20 text-left text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th className="px-4 py-2">OR No</th>
                                <th className="px-4 py-2">Date</th>
                                <th className="px-4 py-2">Method</th>
                                <th className="px-4 py-2">Reference</th>
                                <th className="px-4 py-2 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            {receipts!.map((r) => (
                                <tr key={r.id} className="border-t">
                                    <td className="px-4 py-1.5 font-mono">{r.doc_no}</td>
                                    <td className="px-4 py-1.5">{r.received_date}</td>
                                    <td className="px-4 py-1.5">{PAYMENT_METHOD_LABELS[r.payment_method]}</td>
                                    <td className="px-4 py-1.5 text-xs text-muted-foreground">
                                        {r.reference_no ?? '—'}
                                    </td>
                                    <td className="px-4 py-1.5 text-right tabular-nums">
                                        {formatPhp(r.amount)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {/* ── Posting trail ───────────────────────────────────────── */}
            <Section title="Posting trail" className="mt-6">
                <KV label="Posted at" value={invoice.posted_at ?? '—'} />
                {invoice.journal_entry_id && (
                    <KV
                        label="Journal entry"
                        value={
                            <a
                                href={`#/accounting/journals/${invoice.journal_entry_id}`}
                                className="font-mono text-primary hover:underline"
                            >
                                {invoice.journal_entry_id.slice(0, 8)}…
                            </a>
                        }
                    />
                )}
                {invoice.voided_at && (
                    <>
                        <KV label="Voided at" value={invoice.voided_at} />
                        <KV label="Void reason" value={invoice.void_reason ?? '—'} />
                    </>
                )}
            </Section>

            {voidOpen && (
                <VoidDialog
                    onClose={() => setVoidOpen(false)}
                    onConfirm={(reason) =>
                        voidIt.mutate(
                            { id: invoice.id, reason },
                            { onSuccess: () => setVoidOpen(false) },
                        )
                    }
                    isPending={voidIt.isPending}
                    error={voidIt.error}
                />
            )}

            {orOpen && (
                <IssueOfficialReceiptDialog
                    invoice={invoice}
                    onClose={() => setOrOpen(false)}
                />
            )}
        </div>
    );
}

function Status({ invoice }: { invoice: { posted_at: string | null; voided_at: string | null } }) {
    if (invoice.voided_at) return <span className="text-amber-700">Voided</span>;
    if (invoice.posted_at) return <span className="text-emerald-700">Posted</span>;
    return <span className="text-slate-700">Draft</span>;
}

function Section({
    title,
    children,
    className,
}: {
    title: string;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <section className={`rounded-lg border bg-card p-5 ${className ?? ''}`}>
            <h2 className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                {title}
            </h2>
            <dl className="mt-3 space-y-1.5 text-sm">{children}</dl>
        </section>
    );
}

function KV({
    label,
    value,
    bold,
    muted,
}: {
    label: string;
    value: React.ReactNode;
    bold?: boolean;
    muted?: boolean;
}) {
    return (
        <div
            className={`flex items-center justify-between gap-3 ${bold ? 'border-t pt-2 font-semibold tabular-nums' : ''} ${muted ? 'text-muted-foreground' : ''}`}
        >
            <dt className="text-xs uppercase tracking-wide text-muted-foreground">{label}</dt>
            <dd className="tabular-nums">{value}</dd>
        </div>
    );
}

function VoidDialog({
    onClose,
    onConfirm,
    isPending,
    error,
}: {
    onClose: () => void;
    onConfirm: (reason: string) => void;
    isPending: boolean;
    error: unknown;
}) {
    const [reason, setReason] = useState('');
    const reasonValid = reason.trim().length >= 10;

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            onClick={onClose}
        >
            <form
                onClick={(e) => e.stopPropagation()}
                onSubmit={(e) => {
                    e.preventDefault();
                    if (reasonValid) onConfirm(reason);
                }}
                className="w-full max-w-md space-y-4 rounded-lg bg-card p-6 shadow-lg"
            >
                <header>
                    <h2 className="text-lg font-semibold">Void this invoice?</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Voiding books a reversal journal entry and flags the SI with
                        <code className="mx-1 rounded bg-muted px-1.5 py-0.5 font-mono text-xs">voided_at</code>.
                        The doc_no stays consumed (BIR sequential numbering, no gaps).
                    </p>
                </header>
                <div className="space-y-2">
                    <label className="text-sm font-medium" htmlFor="void-reason">
                        Reason
                    </label>
                    <textarea
                        id="void-reason"
                        rows={4}
                        value={reason}
                        onChange={(e) => setReason(e.target.value)}
                        placeholder="e.g. Customer disputed line 3; reissuing as SI-2026-000234."
                        className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                    />
                    <p className="text-xs text-muted-foreground">
                        {reason.length}/500 chars · min 10 required
                    </p>
                </div>

                {error !== null && error !== undefined && (
                    <div className="rounded-md border border-destructive/30 bg-destructive/5 p-2 text-xs text-destructive">
                        {(error as { response?: { data?: { message?: string } } })?.response?.data
                            ?.message ?? 'Void failed.'}
                    </div>
                )}

                <div className="flex justify-end gap-2">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-md border px-3 py-1.5 text-sm hover:bg-accent"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        disabled={!reasonValid || isPending}
                        className="rounded-md bg-destructive px-3 py-1.5 text-sm font-medium text-destructive-foreground hover:opacity-90 disabled:opacity-50"
                    >
                        {isPending ? 'Voiding…' : 'Void invoice'}
                    </button>
                </div>
            </form>
        </div>
    );
}
