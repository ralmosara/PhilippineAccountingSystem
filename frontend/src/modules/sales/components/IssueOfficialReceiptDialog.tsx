import { useState } from 'react';

import { AccountPicker } from '@/shared/components/AccountPicker';
import { MoneyInput } from '@/shared/components/MoneyInput';
import { formatPhp } from '@/shared/lib/money';

import {
    PAYMENT_METHOD_LABELS,
    useIssueOfficialReceipt,
    type PaymentMethod,
} from '../api/official-receipts';
import { type SalesInvoice } from '../api/sales-invoices';

/**
 * OR issuance dialog. Two launch surfaces:
 *
 *   1. From a cash sales invoice's detail page → amount + customer pre-filled,
 *      sales_invoice_id pinned. Common for retail / POS-style flows.
 *
 *   2. Standalone (charge invoice collection) → user picks the SI being paid,
 *      or issues a generic OR with no SI link (rare; advance payments).
 *
 * The OR is BIR-sequentially-numbered like the SI. The action allocates the
 * doc_no, posts a Cash/AR ↔ AR/Cash JV, and emits OrIssued. EIS submission
 * is NOT triggered for ORs (RR 8-2022 doesn't currently require them).
 */
interface Props {
    invoice: SalesInvoice;
    onClose: () => void;
}

export function IssueOfficialReceiptDialog({ invoice, onClose }: Props) {
    const [amount, setAmount] = useState<string>(invoice.total);
    const [receivedDate, setReceivedDate] = useState(new Date().toISOString().slice(0, 10));
    const [paymentMethod, setPaymentMethod] = useState<PaymentMethod>('cash');
    const [referenceNo, setReferenceNo] = useState('');
    const [remarks, setRemarks] = useState('');
    const [cashAccountId, setCashAccountId] = useState('');
    const [arAccountId, setArAccountId] = useState('');
    const [documentSeriesId, setDocumentSeriesId] = useState('');

    const issue = useIssueOfficialReceipt();

    const referenceRequired = paymentMethod !== 'cash';
    const canSubmit =
        Number(amount) > 0 &&
        cashAccountId &&
        arAccountId &&
        documentSeriesId &&
        (!referenceRequired || referenceNo.trim() !== '');

    const isOverpayment = Number(amount) > Number(invoice.total);
    const isPartial = Number(amount) > 0 && Number(amount) < Number(invoice.total);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!canSubmit) return;
        issue.mutate(
            {
                customer_id: invoice.customer_id,
                sales_invoice_id: invoice.id,
                document_series_id: documentSeriesId,
                received_date: receivedDate,
                amount,
                cash_account_id: cashAccountId,
                ar_account_id: arAccountId,
                payment_method: paymentMethod,
                reference_no: referenceNo || undefined,
                remarks: remarks || undefined,
            },
            { onSuccess: onClose },
        );
    };

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            onClick={onClose}
        >
            <form
                onClick={(e) => e.stopPropagation()}
                onSubmit={handleSubmit}
                className="max-h-[90vh] w-full max-w-xl space-y-4 overflow-y-auto rounded-lg bg-card p-6 shadow-lg"
            >
                <header>
                    <h2 className="text-lg font-semibold">Issue Official Receipt</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Records payment received against{' '}
                        <span className="font-mono">{invoice.doc_no}</span>
                        {invoice.customer_name && <> from <strong>{invoice.customer_name}</strong></>}.
                        OR is BIR-sequentially numbered; posting books{' '}
                        <code className="rounded bg-muted px-1 py-0.5 font-mono text-xs">DR Cash · CR AR</code>.
                    </p>
                </header>

                <div className="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <FieldLabel>Amount received</FieldLabel>
                        <MoneyInput
                            value={amount}
                            onChange={setAmount}
                            withPesoSign
                            className="text-left"
                        />
                        <PaymentStatusHint
                            amount={amount}
                            invoiceTotal={invoice.total}
                            isPartial={isPartial}
                            isOverpayment={isOverpayment}
                        />
                    </div>

                    <div>
                        <FieldLabel>Received date</FieldLabel>
                        <input
                            type="date"
                            value={receivedDate}
                            onChange={(e) => setReceivedDate(e.target.value)}
                            className="w-full rounded-md border bg-background px-2 py-1.5 text-xs"
                        />
                    </div>

                    <div>
                        <FieldLabel>Payment method</FieldLabel>
                        <select
                            value={paymentMethod}
                            onChange={(e) => setPaymentMethod(e.target.value as PaymentMethod)}
                            className="w-full rounded-md border bg-background px-2 py-1.5 text-xs"
                        >
                            {(Object.keys(PAYMENT_METHOD_LABELS) as PaymentMethod[]).map((m) => (
                                <option key={m} value={m}>
                                    {PAYMENT_METHOD_LABELS[m]}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <FieldLabel>
                            Reference no
                            {referenceRequired && <span className="text-destructive"> *</span>}
                        </FieldLabel>
                        <input
                            type="text"
                            value={referenceNo}
                            onChange={(e) => setReferenceNo(e.target.value)}
                            placeholder={
                                paymentMethod === 'check'
                                    ? 'Check #'
                                    : paymentMethod === 'bank_transfer'
                                      ? 'Bank reference'
                                      : paymentMethod === 'gcash' || paymentMethod === 'maya'
                                        ? 'Transaction ID'
                                        : 'Optional'
                            }
                            className="w-full rounded-md border bg-background px-2 py-1.5 text-xs"
                        />
                    </div>

                    <div>
                        <FieldLabel>Cash / bank account</FieldLabel>
                        <AccountPicker
                            value={cashAccountId}
                            onChange={setCashAccountId}
                            filter={(a) => a.type === 'asset'}
                        />
                    </div>

                    <div>
                        <FieldLabel>AR account</FieldLabel>
                        <AccountPicker
                            value={arAccountId}
                            onChange={setArAccountId}
                            filter={(a) => a.type === 'asset'}
                        />
                    </div>

                    <div className="col-span-2">
                        <FieldLabel>OR document series</FieldLabel>
                        <input
                            type="text"
                            value={documentSeriesId}
                            onChange={(e) => setDocumentSeriesId(e.target.value)}
                            placeholder="UUID of active OR series"
                            className="w-full rounded-md border bg-background px-2 py-1.5 font-mono text-xs"
                        />
                    </div>

                    <div className="col-span-2">
                        <FieldLabel>Remarks (optional)</FieldLabel>
                        <input
                            type="text"
                            value={remarks}
                            onChange={(e) => setRemarks(e.target.value)}
                            placeholder="e.g. Partial payment per agreement dated Apr 5"
                            className="w-full rounded-md border bg-background px-2 py-1.5 text-xs"
                        />
                    </div>
                </div>

                {issue.isError && (
                    <div className="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-xs text-destructive">
                        {(issue.error as { response?: { data?: { message?: string } } })?.response?.data
                            ?.message ?? 'OR issuance failed.'}
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
                        type="submit"
                        disabled={!canSubmit || issue.isPending}
                        className="rounded-md bg-primary px-4 py-1.5 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                    >
                        {issue.isPending ? 'Issuing…' : 'Issue OR'}
                    </button>
                </div>
            </form>
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

function PaymentStatusHint({
    amount,
    invoiceTotal,
    isPartial,
    isOverpayment,
}: {
    amount: string;
    invoiceTotal: string;
    isPartial: boolean;
    isOverpayment: boolean;
}) {
    if (isOverpayment) {
        const excess = Number(amount) - Number(invoiceTotal);
        return (
            <p className="mt-1 text-xs text-amber-700">
                ⚠ Overpayment of {formatPhp(excess.toString())} — the excess will sit as an AR credit balance.
            </p>
        );
    }
    if (isPartial) {
        const remaining = Number(invoiceTotal) - Number(amount);
        return (
            <p className="mt-1 text-xs text-muted-foreground">
                Partial payment · {formatPhp(remaining.toString())} remaining on the invoice.
            </p>
        );
    }
    if (Number(amount) > 0) {
        return (
            <p className="mt-1 text-xs text-emerald-700">
                ✓ Fully settles the invoice.
            </p>
        );
    }
    return null;
}
