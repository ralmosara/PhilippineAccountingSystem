import { useState } from 'react';

import { CustomerPicker } from '@/shared/components/CustomerPicker';
import { MoneyInput } from '@/shared/components/MoneyInput';
import { formatPhp } from '@/shared/lib/money';
import { fromScaled, sumScaled, toScaled } from '@/shared/lib/bcmath';

import { useIssueOfficialReceipt, type PaymentMethod, PAYMENT_METHOD_LABELS } from '../api/official-receipts';
import { useIssueSalesInvoice, type Customer } from '../api/sales-invoices';

/**
 * POS Terminal — kiosk-mode page for counter sales. Optimised for:
 *
 *   - Walk-in customer entry (or a "WALK-IN" generic customer pinned in settings)
 *   - Fast line entry (description + qty + price; no account picker per line)
 *   - One-tap payment (cash by default; modal swap to GCash/Maya)
 *   - Auto-issues SI + OR in sequence, prints both
 *
 * No keyboard-shortcut help shown by default — POS users learn the flow once
 * and run thousands of transactions on muscle memory.
 *
 * Items shortcut: hard-coded list of last-used items would go here in Phase 2;
 * for now the description field is free-text.
 */

interface CartLine {
    description: string;
    quantity: string;
    unit_price: string;
}

const blankLine = (): CartLine => ({ description: '', quantity: '1', unit_price: '' });

export function PosTerminalPage() {
    const [customer, setCustomer] = useState<Customer | null>(null);
    const [customerId, setCustomerId] = useState('');
    const [lines, setLines] = useState<CartLine[]>([blankLine()]);
    const [paymentMethod, setPaymentMethod] = useState<PaymentMethod>('cash');
    const [referenceNo, setReferenceNo] = useState('');
    const [stage, setStage] = useState<'cart' | 'payment' | 'done'>('cart');
    const [issuedDocNos, setIssuedDocNos] = useState<{ si: string; or: string } | null>(null);

    // Hard-coded series IDs / accounts — production deployment plugs these in
    // via per-terminal config that arrives from /pos/terminal-config on mount.
    // For Phase 1 the operator pastes them once via localStorage settings.
    const [terminalConfig] = useState({
        si_document_series_id: localStorage.getItem('pos.si_series') ?? '',
        or_document_series_id: localStorage.getItem('pos.or_series') ?? '',
        ar_account_id: localStorage.getItem('pos.ar_account') ?? '',
        vat_payable_account_id: localStorage.getItem('pos.vat_account') ?? '',
        cash_account_id: localStorage.getItem('pos.cash_account') ?? '',
        revenue_account_id: localStorage.getItem('pos.revenue_account') ?? '',
    });

    const issueSi = useIssueSalesInvoice();
    const issueOr = useIssueOfficialReceipt();

    // ── Cart totals ──────────────────────────────────────────────────────
    const isExempt = customer?.is_senior_citizen || customer?.is_pwd;
    const lineNets = lines.map((l) => (toScaled(l.quantity) * toScaled(l.unit_price)) / 10n ** 4n);
    const subtotal = sumScaled(lineNets.map((n) => fromScaled(n, 4)));
    let total = subtotal;
    if (!isExempt) {
        const vat = (subtotal * 12n) / 100n;
        total = subtotal + vat;
    } else {
        const discount = (subtotal * 20n) / 100n;
        total = subtotal - discount;
    }

    const canCheckout =
        customer && lines.every((l) => l.description && l.quantity && l.unit_price) && total > 0n;

    const handleCheckout = async () => {
        if (!canCheckout) return;
        try {
            // 1. Issue SI
            const si = await issueSi.mutateAsync({
                customer_id: customerId,
                document_series_id: terminalConfig.si_document_series_id,
                invoice_date: new Date().toISOString().slice(0, 10),
                doc_kind: 'cash',
                ar_account_id: terminalConfig.ar_account_id,
                vat_payable_account_id: terminalConfig.vat_payable_account_id,
                lines: lines.map((l) => ({
                    description: l.description,
                    quantity: l.quantity,
                    unit_price: l.unit_price,
                    tax_kind: isExempt ? 'vat_exempt' : 'vat_output',
                    revenue_account_id: terminalConfig.revenue_account_id,
                    discount_pct: isExempt ? '20' : '0',
                })),
            });

            // 2. Issue OR for the full amount
            const or = await issueOr.mutateAsync({
                customer_id: customerId,
                sales_invoice_id: si.id,
                document_series_id: terminalConfig.or_document_series_id,
                received_date: new Date().toISOString().slice(0, 10),
                amount: si.total,
                cash_account_id: terminalConfig.cash_account_id,
                ar_account_id: terminalConfig.ar_account_id,
                payment_method: paymentMethod,
                reference_no: referenceNo || undefined,
            });

            setIssuedDocNos({ si: si.doc_no, or: or.doc_no });
            setStage('done');
        } catch (e) {
            // Errors are reflected in the mutation states; banner below catches them
            console.error('POS checkout failed', e);
        }
    };

    const reset = () => {
        setCustomer(null);
        setCustomerId('');
        setLines([blankLine()]);
        setPaymentMethod('cash');
        setReferenceNo('');
        setStage('cart');
        setIssuedDocNos(null);
        issueSi.reset();
        issueOr.reset();
    };

    const updateLine = (i: number, patch: Partial<CartLine>) =>
        setLines((prev) => prev.map((l, idx) => (idx === i ? { ...l, ...patch } : l)));

    const configMissing = Object.values(terminalConfig).some((v) => v === '');

    return (
        <div className="container max-w-5xl py-6">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">POS Terminal</h1>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Counter-sale mode. Cash SI + OR issued in one transaction; JV posted automatically.
                    </p>
                </div>
                {stage !== 'cart' && (
                    <button
                        type="button"
                        onClick={reset}
                        className="rounded-md border px-3 py-1.5 text-sm hover:bg-accent"
                    >
                        New sale
                    </button>
                )}
            </header>

            {configMissing && (
                <div className="mt-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                    <strong>Terminal not configured.</strong> Set the SI/OR document series and the default
                    cash / AR / VAT / revenue account IDs via your operations admin before transacting.
                    Currently these are read from <code>localStorage</code> under{' '}
                    <code className="font-mono">pos.*</code> keys.
                </div>
            )}

            {stage === 'cart' && (
                <CartStage
                    customer={customer}
                    customerId={customerId}
                    setCustomerId={setCustomerId}
                    setCustomer={setCustomer}
                    lines={lines}
                    updateLine={updateLine}
                    addLine={() => setLines((p) => [...p, blankLine()])}
                    removeLine={(i) =>
                        setLines((p) => (p.length <= 1 ? p : p.filter((_, idx) => idx !== i)))
                    }
                    subtotal={subtotal}
                    total={total}
                    isExempt={!!isExempt}
                    canCheckout={!!canCheckout && !configMissing}
                    onCheckout={() => setStage('payment')}
                />
            )}

            {stage === 'payment' && (
                <PaymentStage
                    total={total}
                    paymentMethod={paymentMethod}
                    setPaymentMethod={setPaymentMethod}
                    referenceNo={referenceNo}
                    setReferenceNo={setReferenceNo}
                    onBack={() => setStage('cart')}
                    onConfirm={handleCheckout}
                    isProcessing={issueSi.isPending || issueOr.isPending}
                    error={issueSi.error ?? issueOr.error}
                />
            )}

            {stage === 'done' && issuedDocNos && (
                <DoneStage docs={issuedDocNos} onNewSale={reset} />
            )}
        </div>
    );
}

function CartStage({
    customer,
    customerId,
    setCustomerId,
    setCustomer,
    lines,
    updateLine,
    addLine,
    removeLine,
    subtotal,
    total,
    isExempt,
    canCheckout,
    onCheckout,
}: {
    customer: Customer | null;
    customerId: string;
    setCustomerId: (id: string) => void;
    setCustomer: (c: Customer | null) => void;
    lines: CartLine[];
    updateLine: (i: number, patch: Partial<CartLine>) => void;
    addLine: () => void;
    removeLine: (i: number) => void;
    subtotal: bigint;
    total: bigint;
    isExempt: boolean;
    canCheckout: boolean;
    onCheckout: () => void;
}) {
    return (
        <div className="mt-6 grid grid-cols-3 gap-6">
            <div className="col-span-2 space-y-4">
                <section>
                    <h2 className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        Customer
                    </h2>
                    <div className="mt-2">
                        <CustomerPicker
                            value={customerId}
                            onChange={(id, c) => { setCustomerId(id); setCustomer(c); }}
                            className=""
                        />
                    </div>
                    {customer && isExempt && (
                        <p className="mt-2 text-xs text-amber-700">
                            Senior / PWD: applying 20% statutory discount, lines marked VAT-exempt.
                        </p>
                    )}
                </section>

                <section>
                    <h2 className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        Cart
                    </h2>
                    <div className="mt-2 overflow-hidden rounded-lg border bg-card">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th className="px-2 py-2">Item / description</th>
                                    <th className="w-24 px-2 py-2 text-right">Qty</th>
                                    <th className="w-32 px-2 py-2 text-right">Price</th>
                                    <th className="w-32 px-2 py-2 text-right">Line total</th>
                                    <th className="w-8"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {lines.map((l, i) => {
                                    const lineNet =
                                        (toScaled(l.quantity) * toScaled(l.unit_price)) / 10n ** 4n;
                                    return (
                                        <tr key={i} className="border-t">
                                            <td className="px-2 py-1">
                                                <input
                                                    type="text"
                                                    value={l.description}
                                                    onChange={(e) => updateLine(i, { description: e.target.value })}
                                                    placeholder="Item name"
                                                    className="w-full rounded-md border bg-background px-2 py-1.5 text-sm"
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
                                            <td className="px-2 py-1 text-right font-mono text-sm tabular-nums">
                                                {formatPhp(fromScaled(lineNet, 2))}
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
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    <button
                        type="button"
                        onClick={addLine}
                        className="mt-2 rounded-md border border-dashed px-3 py-1.5 text-xs text-muted-foreground hover:bg-accent"
                    >
                        + Add line
                    </button>
                </section>
            </div>

            <aside className="space-y-4">
                <div className="rounded-lg border bg-card p-5">
                    <h3 className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        Order summary
                    </h3>
                    <dl className="mt-3 space-y-1.5 text-sm tabular-nums">
                        <div className="flex justify-between">
                            <dt>Subtotal</dt>
                            <dd>{formatPhp(fromScaled(subtotal, 2))}</dd>
                        </div>
                        {isExempt ? (
                            <div className="flex justify-between text-amber-700">
                                <dt>Less: 20% Senior/PWD discount</dt>
                                <dd>({formatPhp(fromScaled((subtotal * 20n) / 100n, 2))})</dd>
                            </div>
                        ) : (
                            <div className="flex justify-between text-muted-foreground">
                                <dt>VAT 12%</dt>
                                <dd>{formatPhp(fromScaled((subtotal * 12n) / 100n, 2))}</dd>
                            </div>
                        )}
                        <div className="flex justify-between border-t pt-2 text-lg font-semibold">
                            <dt>Total</dt>
                            <dd>{formatPhp(fromScaled(total, 2))}</dd>
                        </div>
                    </dl>

                    <button
                        type="button"
                        onClick={onCheckout}
                        disabled={!canCheckout}
                        className="mt-4 w-full rounded-md bg-primary px-4 py-3 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                    >
                        Checkout
                    </button>
                </div>
            </aside>
        </div>
    );
}

function PaymentStage({
    total,
    paymentMethod,
    setPaymentMethod,
    referenceNo,
    setReferenceNo,
    onBack,
    onConfirm,
    isProcessing,
    error,
}: {
    total: bigint;
    paymentMethod: PaymentMethod;
    setPaymentMethod: (m: PaymentMethod) => void;
    referenceNo: string;
    setReferenceNo: (s: string) => void;
    onBack: () => void;
    onConfirm: () => void;
    isProcessing: boolean;
    error: unknown;
}) {
    const referenceRequired = paymentMethod !== 'cash';
    const canConfirm = !referenceRequired || referenceNo.trim() !== '';

    return (
        <div className="mt-6 mx-auto max-w-md space-y-5 rounded-lg border bg-card p-6">
            <div className="text-center">
                <div className="text-xs uppercase tracking-wide text-muted-foreground">
                    Amount due
                </div>
                <div className="mt-1 text-4xl font-bold tabular-nums">
                    {formatPhp(fromScaled(total, 2))}
                </div>
            </div>

            <div className="space-y-2">
                <label className="text-sm font-medium">Payment method</label>
                <div className="grid grid-cols-3 gap-2">
                    {(Object.keys(PAYMENT_METHOD_LABELS) as PaymentMethod[]).map((m) => (
                        <button
                            key={m}
                            type="button"
                            onClick={() => setPaymentMethod(m)}
                            className={`rounded-md border px-2 py-2 text-xs ${
                                paymentMethod === m ? 'border-primary bg-primary/5 font-medium' : 'hover:bg-accent'
                            }`}
                        >
                            {PAYMENT_METHOD_LABELS[m]}
                        </button>
                    ))}
                </div>
            </div>

            {referenceRequired && (
                <div className="space-y-2">
                    <label className="text-sm font-medium">Reference number</label>
                    <input
                        type="text"
                        value={referenceNo}
                        onChange={(e) => setReferenceNo(e.target.value)}
                        placeholder={
                            paymentMethod === 'check' ? 'Check #'
                            : paymentMethod === 'bank_transfer' ? 'Bank reference'
                            : 'Transaction ID'
                        }
                        autoFocus
                        className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                    />
                </div>
            )}

            {error !== null && error !== undefined && (
                <div className="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                    {(error as { response?: { data?: { message?: string } } })?.response?.data
                        ?.message ?? 'Checkout failed.'}
                </div>
            )}

            <div className="flex gap-2">
                <button
                    type="button"
                    onClick={onBack}
                    disabled={isProcessing}
                    className="rounded-md border px-4 py-2 text-sm hover:bg-accent disabled:opacity-50"
                >
                    Back
                </button>
                <button
                    type="button"
                    onClick={onConfirm}
                    disabled={!canConfirm || isProcessing}
                    className="flex-1 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                >
                    {isProcessing ? 'Processing…' : 'Confirm payment'}
                </button>
            </div>
        </div>
    );
}

function DoneStage({ docs, onNewSale }: { docs: { si: string; or: string }; onNewSale: () => void }) {
    return (
        <div className="mt-6 mx-auto max-w-md space-y-4 rounded-lg border border-emerald-200 bg-emerald-50 p-6 text-center">
            <div className="text-5xl">✓</div>
            <h2 className="text-lg font-semibold text-emerald-900">Sale completed</h2>
            <dl className="space-y-1 text-sm text-emerald-900">
                <div className="flex justify-between border-t border-emerald-200 pt-2">
                    <dt>Sales Invoice</dt>
                    <dd className="font-mono">{docs.si}</dd>
                </div>
                <div className="flex justify-between">
                    <dt>Official Receipt</dt>
                    <dd className="font-mono">{docs.or}</dd>
                </div>
            </dl>
            <p className="text-xs text-emerald-700">
                Print or hand the documents to the customer; journal entry already posted.
            </p>
            <button
                type="button"
                onClick={onNewSale}
                className="w-full rounded-md bg-emerald-600 px-4 py-3 text-sm font-medium text-white hover:bg-emerald-700"
            >
                New sale
            </button>
        </div>
    );
}
