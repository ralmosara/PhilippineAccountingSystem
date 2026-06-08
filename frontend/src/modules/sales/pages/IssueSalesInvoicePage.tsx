import { useEffect, useMemo, useState } from 'react';

import { AccountPicker } from '@/shared/components/AccountPicker';
import { CustomerPicker } from '@/shared/components/CustomerPicker';
import { MoneyInput } from '@/shared/components/MoneyInput';
import { formatPhp } from '@/shared/lib/money';
import { fromScaled, sumScaled, toScaled } from '@/shared/lib/bcmath';

import {
    useIssueSalesInvoice,
    type Customer,
    type IssueInvoiceInput,
} from '../api/sales-invoices';

/**
 * Issue Sales Invoice page.
 *
 * Layout mirrors the JV editor: header fields up top, line table beneath,
 * live VAT/total preview at the bottom right. Keyboard ergonomics:
 *   - Tab cycles cells, Enter on last cell adds a row
 *   - Ctrl/Cmd+S submits (issue + post + queue EIS)
 *
 * VAT preview reflects the customer-class side-effects:
 *   - Senior or PWD  →  20% line discount + VAT-exempt classification
 *   - Government     →  5% withheld VAT (BIR remits directly)
 *   - Standard       →  12% output VAT
 *
 * Final numbers (incl. server-side rounding) come back from the API on submit.
 */

interface LineRow {
    description: string;
    quantity: string;
    unit_price: string;
    discount_pct: string;
    tax_kind: 'vat_output' | 'vat_zero' | 'vat_exempt';
    revenue_account_id: string;
}

const blankLine = (): LineRow => ({
    description: '',
    quantity: '1',
    unit_price: '',
    discount_pct: '0',
    tax_kind: 'vat_output',
    revenue_account_id: '',
});

export function IssueSalesInvoicePage() {
    const [customer, setCustomer] = useState<Customer | null>(null);
    const [customerId, setCustomerId] = useState<string>('');
    const [arAccountId, setArAccountId] = useState<string>('');
    const [vatPayableAccountId, setVatPayableAccountId] = useState<string>('');
    const [documentSeriesId, setDocumentSeriesId] = useState<string>('');
    const [docKind, setDocKind] = useState<'cash' | 'charge'>('charge');
    const [invoiceDate, setInvoiceDate] = useState(new Date().toISOString().slice(0, 10));
    const [dueDate, setDueDate] = useState<string>('');
    const [lines, setLines] = useState<LineRow[]>([blankLine()]);

    const issue = useIssueSalesInvoice();

    // ── Auto-classify customer side-effects ──────────────────────────────
    useEffect(() => {
        if (!customer) return;
        // Senior / PWD → all lines become VAT-exempt with 20% statutory discount
        if (customer.is_senior_citizen || customer.is_pwd) {
            setLines((prev) =>
                prev.map((l) => ({ ...l, tax_kind: 'vat_exempt', discount_pct: l.discount_pct || '20' })),
            );
        }
        // Default payment terms
        if (customer.payment_terms_days > 0 && !dueDate) {
            const due = new Date(invoiceDate);
            due.setDate(due.getDate() + customer.payment_terms_days);
            setDueDate(due.toISOString().slice(0, 10));
        }
    }, [customer, invoiceDate, dueDate]);

    // ── Live computation (preview only — server is source of truth) ──────
    const totals = useMemo(() => {
        const vatable = sumScaled(
            lines.filter((l) => l.tax_kind === 'vat_output').map((l) => lineNet(l)),
        );
        const zeroRated = sumScaled(
            lines.filter((l) => l.tax_kind === 'vat_zero').map((l) => lineNet(l)),
        );
        const exempt = sumScaled(
            lines.filter((l) => l.tax_kind === 'vat_exempt').map((l) => lineNet(l)),
        );

        // VAT = vatable * 12%; computed as scaled-int multiplication then /100
        const vat = (vatable * 12n) / 100n;
        const subtotal = vatable + zeroRated + exempt;
        let total = subtotal + vat;

        // Government → 5% withheld VAT (deducts from the receivable)
        let withheldVat = 0n;
        if (customer?.is_government) {
            withheldVat = (vatable * 5n) / 100n;
            total = total - withheldVat;
        }

        return {
            vatable,
            zeroRated,
            exempt,
            vat,
            subtotal,
            withheldVat,
            total,
        };
    }, [lines, customer]);

    const canSubmit =
        customer &&
        documentSeriesId &&
        arAccountId &&
        vatPayableAccountId &&
        lines.length > 0 &&
        lines.every((l) => l.description && l.quantity && l.unit_price && l.revenue_account_id);

    const submit = () => {
        if (!canSubmit) return;
        const payload: IssueInvoiceInput = {
            customer_id: customerId,
            document_series_id: documentSeriesId,
            invoice_date: invoiceDate,
            due_date: dueDate || undefined,
            doc_kind: docKind,
            ar_account_id: arAccountId,
            vat_payable_account_id: vatPayableAccountId,
            lines: lines.map((l) => ({
                description: l.description,
                quantity: l.quantity,
                unit_price: l.unit_price,
                tax_kind: l.tax_kind,
                revenue_account_id: l.revenue_account_id,
                discount_pct: l.discount_pct || '0',
            })),
        };
        issue.mutate(payload, {
            onSuccess: (inv) => {
                window.location.hash = `#/sales/invoices/${inv.id}`;
            },
        });
    };

    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                submit();
            }
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    });

    const updateLine = (i: number, patch: Partial<LineRow>) =>
        setLines((prev) => prev.map((l, idx) => (idx === i ? { ...l, ...patch } : l)));
    const addLine = () => setLines((prev) => [...prev, blankLine()]);
    const removeLine = (i: number) =>
        setLines((prev) => (prev.length <= 1 ? prev : prev.filter((_, idx) => idx !== i)));

    return (
        <div className="container py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">Issue Sales Invoice</h1>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Allocates SI number from the active series, computes VAT, posts the JV, and
                        queues EIS submission. Ctrl+S to submit.
                    </p>
                </div>
                <div className="flex gap-2 text-sm">
                    <a href="#/sales/invoices" className="rounded-md border px-3 py-1.5 hover:bg-accent">
                        Cancel
                    </a>
                    <button
                        type="button"
                        onClick={submit}
                        disabled={!canSubmit || issue.isPending}
                        className="rounded-md bg-primary px-3 py-1.5 font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                    >
                        {issue.isPending ? 'Issuing…' : 'Issue invoice'}
                    </button>
                </div>
            </header>

            {/* ── Customer + dates ─────────────────────────────────── */}
            <div className="mt-4 grid grid-cols-3 gap-4">
                <div>
                    <FieldLabel>Customer</FieldLabel>
                    <CustomerPicker
                        value={customerId}
                        onChange={(id, c) => {
                            setCustomerId(id);
                            setCustomer(c);
                        }}
                    />
                </div>
                <div>
                    <FieldLabel>Invoice date</FieldLabel>
                    <input
                        type="date"
                        value={invoiceDate}
                        onChange={(e) => setInvoiceDate(e.target.value)}
                        className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                    />
                </div>
                <div>
                    <FieldLabel>Due date {customer?.payment_terms_days ? `(net ${customer.payment_terms_days})` : ''}</FieldLabel>
                    <input
                        type="date"
                        value={dueDate}
                        onChange={(e) => setDueDate(e.target.value)}
                        className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                    />
                </div>
            </div>

            <div className="mt-3 grid grid-cols-4 gap-4">
                <div>
                    <FieldLabel>Doc kind</FieldLabel>
                    <select
                        value={docKind}
                        onChange={(e) => setDocKind(e.target.value as 'cash' | 'charge')}
                        className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                    >
                        <option value="charge">Charge (Accounts Receivable)</option>
                        <option value="cash">Cash (issue OR at payment)</option>
                    </select>
                </div>
                <div>
                    <FieldLabel>AR account</FieldLabel>
                    <AccountPicker value={arAccountId} onChange={setArAccountId} />
                </div>
                <div>
                    <FieldLabel>VAT payable account</FieldLabel>
                    <AccountPicker value={vatPayableAccountId} onChange={setVatPayableAccountId} />
                </div>
                <div>
                    <FieldLabel>Document series</FieldLabel>
                    <input
                        type="text"
                        value={documentSeriesId}
                        onChange={(e) => setDocumentSeriesId(e.target.value)}
                        placeholder="UUID of active SI series"
                        className="w-full rounded-md border bg-background px-3 py-2 font-mono text-xs"
                    />
                </div>
            </div>

            {/* ── Customer flags surface ────────────────────────────── */}
            {customer && <CustomerFlags customer={customer} />}

            {/* ── Lines table ──────────────────────────────────────── */}
            <div className="mt-4 overflow-hidden rounded-lg border bg-card">
                <table className="w-full text-sm">
                    <thead className="bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th className="w-12 px-2 py-2 text-right">#</th>
                            <th className="px-2 py-2">Description</th>
                            <th className="w-24 px-2 py-2 text-right">Qty</th>
                            <th className="w-32 px-2 py-2 text-right">Unit Price</th>
                            <th className="w-20 px-2 py-2 text-right">Disc %</th>
                            <th className="w-28 px-2 py-2">VAT</th>
                            <th className="px-2 py-2">Revenue Account</th>
                            <th className="w-32 px-2 py-2 text-right">Net</th>
                            <th className="w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {lines.map((l, i) => (
                            <tr key={i} className="border-t">
                                <td className="px-2 py-1 text-right text-muted-foreground tabular-nums">
                                    {i + 1}
                                </td>
                                <td className="px-2 py-1">
                                    <input
                                        type="text"
                                        value={l.description}
                                        onChange={(e) => updateLine(i, { description: e.target.value })}
                                        className="w-full rounded-md border bg-background px-2 py-1 text-xs"
                                    />
                                </td>
                                <td className="px-2 py-1">
                                    <MoneyInput
                                        value={l.quantity}
                                        onChange={(v) => updateLine(i, { quantity: v })}
                                        displayDp={4}
                                    />
                                </td>
                                <td className="px-2 py-1">
                                    <MoneyInput
                                        value={l.unit_price}
                                        onChange={(v) => updateLine(i, { unit_price: v })}
                                    />
                                </td>
                                <td className="px-2 py-1">
                                    <MoneyInput
                                        value={l.discount_pct}
                                        onChange={(v) => updateLine(i, { discount_pct: v })}
                                    />
                                </td>
                                <td className="px-2 py-1">
                                    <select
                                        value={l.tax_kind}
                                        onChange={(e) =>
                                            updateLine(i, {
                                                tax_kind: e.target.value as LineRow['tax_kind'],
                                            })
                                        }
                                        className="w-full rounded-md border bg-background px-1 py-1 text-xs"
                                    >
                                        <option value="vat_output">VAT 12%</option>
                                        <option value="vat_zero">Zero-rated</option>
                                        <option value="vat_exempt">Exempt</option>
                                    </select>
                                </td>
                                <td className="px-2 py-1">
                                    <AccountPicker
                                        value={l.revenue_account_id}
                                        onChange={(id) => updateLine(i, { revenue_account_id: id })}
                                        filter={(a) => a.type === 'revenue'}
                                    />
                                </td>
                                <td className="px-2 py-1 text-right font-mono text-xs tabular-nums">
                                    {formatPhp(fromScaled(lineNet(l), 2))}
                                </td>
                                <td className="px-2 py-1 text-right">
                                    <button
                                        type="button"
                                        onClick={() => removeLine(i)}
                                        disabled={lines.length <= 1}
                                        className="text-xs text-muted-foreground hover:text-destructive disabled:opacity-30"
                                    >
                                        ✕
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <button
                type="button"
                onClick={addLine}
                className="mt-3 rounded-md border border-dashed px-3 py-1.5 text-xs text-muted-foreground hover:bg-accent"
            >
                + Add line
            </button>

            {/* ── Totals preview ───────────────────────────────────── */}
            <TotalsPreview totals={totals} customer={customer} />

            {issue.isError && (
                <div className="mt-4 rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                    {(issue.error as { response?: { data?: { message?: string } } })?.response?.data
                        ?.message ?? 'Issue failed.'}
                </div>
            )}
        </div>
    );
}

function FieldLabel({ children }: { children: React.ReactNode }) {
    return (
        <label className="block text-xs font-medium uppercase tracking-wide text-muted-foreground">
            {children}
        </label>
    );
}

function CustomerFlags({ customer }: { customer: Customer }) {
    const flags: Array<{ label: string; tone: 'amber' | 'purple' | 'slate' }> = [];
    if (customer.is_senior_citizen)  flags.push({ label: 'Senior (RA 9994): 20% discount + VAT-exempt', tone: 'amber' });
    if (customer.is_pwd)             flags.push({ label: 'PWD (RA 10754): 20% discount + VAT-exempt', tone: 'amber' });
    if (customer.is_government)      flags.push({ label: 'Government: 5% withheld VAT', tone: 'purple' });
    if (!customer.is_vat_registered && !customer.is_senior_citizen && !customer.is_pwd && !customer.is_government) {
        flags.push({ label: 'Non-VAT customer', tone: 'slate' });
    }
    if (flags.length === 0) return null;

    return (
        <div className="mt-3 flex flex-wrap gap-2 text-xs">
            {flags.map((f, i) => {
                const colour =
                    f.tone === 'amber' ? 'border-amber-200 bg-amber-50 text-amber-800'
                    : f.tone === 'purple' ? 'border-purple-200 bg-purple-50 text-purple-800'
                    : 'border-slate-200 bg-slate-50 text-slate-700';
                return (
                    <span key={i} className={`rounded-md border px-2 py-1 ${colour}`}>
                        {f.label}
                    </span>
                );
            })}
        </div>
    );
}

function TotalsPreview({
    totals,
    customer,
}: {
    totals: {
        vatable: bigint;
        zeroRated: bigint;
        exempt: bigint;
        vat: bigint;
        subtotal: bigint;
        withheldVat: bigint;
        total: bigint;
    };
    customer: Customer | null;
}) {
    return (
        <div className="mt-4 ml-auto w-full max-w-md rounded-lg border bg-card p-4 text-sm">
            <h3 className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                Computed totals (preview)
            </h3>
            <dl className="mt-2 space-y-1 tabular-nums">
                <Row label="Vatable sales" value={formatPhp(fromScaled(totals.vatable, 2))} />
                {totals.zeroRated > 0n && (
                    <Row label="Zero-rated sales" value={formatPhp(fromScaled(totals.zeroRated, 2))} />
                )}
                {totals.exempt > 0n && (
                    <Row label="Exempt sales" value={formatPhp(fromScaled(totals.exempt, 2))} />
                )}
                <Row label="Output VAT (12%)" value={formatPhp(fromScaled(totals.vat, 2))} />
                {customer?.is_government && totals.withheldVat > 0n && (
                    <Row
                        label="Less: Withheld VAT (5%)"
                        value={`(${formatPhp(fromScaled(totals.withheldVat, 2))})`}
                        muted
                    />
                )}
                <Row
                    label="Net amount due"
                    value={formatPhp(fromScaled(totals.total, 2))}
                    bold
                />
            </dl>
            <p className="mt-3 text-[10px] text-muted-foreground">
                Server-computed totals returned on submit may differ by rounding —
                the API is source of truth.
            </p>
        </div>
    );
}

function Row({ label, value, bold, muted }: { label: string; value: string; bold?: boolean; muted?: boolean }) {
    return (
        <div
            className={`flex items-center justify-between ${bold ? 'border-t pt-2 font-semibold' : ''} ${muted ? 'text-muted-foreground' : ''}`}
        >
            <dt>{label}</dt>
            <dd>{value}</dd>
        </div>
    );
}

/** Net = qty * unit_price * (1 - discount_pct/100), scaled to BigInt. */
function lineNet(l: LineRow): bigint {
    const qty = toScaled(l.quantity);
    const unit = toScaled(l.unit_price);
    const gross = (qty * unit) / 10n ** 4n;          // SCALE_FACTOR
    const discPctScaled = toScaled(l.discount_pct);  // e.g. "20" → 200000n
    const discFactor = 10n ** 4n - discPctScaled / 100n;
    return (gross * discFactor) / 10n ** 4n;
}
