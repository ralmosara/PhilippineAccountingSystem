import { useEffect, useMemo, useState } from 'react';

import { AccountPicker } from '@/shared/components/AccountPicker';
import { MoneyInput } from '@/shared/components/MoneyInput';
import { formatPhp } from '@/shared/lib/money';
import { fromScaled, sumScaled, toScaled } from '@/shared/lib/bcmath';

import { usePostVendorBill, useVendors, type Vendor } from '../api/vendor-bills';

interface LineRow {
    description: string;
    amount: string;
    atc_code: string;
    expense_account_id: string;
}

const blankLine = (atc = ''): LineRow => ({
    description: '',
    amount: '',
    atc_code: atc,
    expense_account_id: '',
});

/**
 * Common ATC withholding rates — preview only, server-side rates from
 * tax.atc_codes are authoritative. Keep this list narrow; we'd rather show
 * "0" for an unknown code (and reveal the operator typed an invalid ATC)
 * than guess.
 */
const ATC_RATE_PREVIEW: Record<string, string> = {
    WC010: '0.01', WC020: '0.02', WC156: '0.02',
    WI010: '0.05', WI011: '0.10', WI012: '0.15',
    WV010: '0.01', WI070: '0.15',
};

/**
 * Post a vendor bill. Computes withholding tax preview based on the line's
 * ATC code (server is source of truth for the actual rates from the
 * tax.atc_codes table); operator sees a running estimate so they spot
 * obviously wrong ATCs before posting.
 */
export function PostVendorBillPage() {
    const [vendorId, setVendorId] = useState('');
    const [vendor, setVendor] = useState<Vendor | null>(null);
    const [vendorInvoiceNo, setVendorInvoiceNo] = useState('');
    const [billDate, setBillDate] = useState(new Date().toISOString().slice(0, 10));
    const [apAccountId, setApAccountId] = useState('');
    const [vatInputAccountId, setVatInputAccountId] = useState('');
    const [whtPayableAccountId, setWhtPayableAccountId] = useState('');
    const [lines, setLines] = useState<LineRow[]>([blankLine()]);

    const { data: vendors } = useVendors();
    const post = usePostVendorBill();

    useEffect(() => {
        const found = vendors?.find((v) => v.id === vendorId);
        setVendor(found ?? null);
        if (found?.default_atc_code) {
            setLines((prev) => prev.map((l) => ({ ...l, atc_code: l.atc_code || found.default_atc_code! })));
        }
    }, [vendorId, vendors]);

    // Per-line withholding preview — rates per ATC are pulled by the server;
    // here we lookup the common ones for an at-a-glance check. Defined as a
    // module-scope constant (not state) so the `useMemo` dep array stays
    // honest — ATC rates don't change between renders.
    const wht = useMemo(
        () =>
            sumScaled(
                lines.map((l) => {
                    const rate = ATC_RATE_PREVIEW[l.atc_code] ?? '0';
                    return ((toScaled(l.amount) * toScaled(rate)) / 10n ** 4n).toString();
                }),
            ),
        [lines],
    );
    const subtotal = sumScaled(lines.map((l) => l.amount));
    // VAT input — assume 12% for VAT vendor; real classification comes back from server.
    const vat = vendor?.is_vat_registered ? (subtotal * 12n) / 100n : 0n;

    const canSubmit =
        vendor &&
        vendorInvoiceNo &&
        apAccountId &&
        (!vendor.is_vat_registered || vatInputAccountId) &&
        lines.every((l) => l.description && l.amount && l.expense_account_id);

    const submit = () => {
        if (!canSubmit) return;
        post.mutate(
            {
                vendor_id: vendorId,
                vendor_invoice_no: vendorInvoiceNo,
                bill_date: billDate,
                ap_account_id: apAccountId,
                vat_input_account_id: vatInputAccountId || undefined,
                withholding_payable_account_id: whtPayableAccountId || undefined,
                lines: lines.map((l) => ({
                    description: l.description,
                    amount: l.amount,
                    atc_code: l.atc_code || null,
                    expense_account_id: l.expense_account_id,
                })),
            },
            { onSuccess: (b) => (window.location.hash = `#/procurement/bills/${b.id}`) },
        );
    };

    const updateLine = (i: number, patch: Partial<LineRow>) =>
        setLines((prev) => prev.map((l, idx) => (idx === i ? { ...l, ...patch } : l)));

    return (
        <div className="container py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">Post Vendor Bill</h1>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Books the JV (DR Expense / VAT Input · CR AP / WHT Payable), issues Form 2307
                        to the vendor. ATC code drives the withholding rate.
                    </p>
                </div>
                <div className="flex gap-2 text-sm">
                    <a href="#/procurement/bills" className="rounded-md border px-3 py-1.5 hover:bg-accent">
                        Cancel
                    </a>
                    <button
                        type="button"
                        onClick={submit}
                        disabled={!canSubmit || post.isPending}
                        className="rounded-md bg-primary px-3 py-1.5 font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                    >
                        {post.isPending ? 'Posting…' : 'Post bill'}
                    </button>
                </div>
            </header>

            <div className="mt-4 grid grid-cols-3 gap-4">
                <div>
                    <FieldLabel>Vendor</FieldLabel>
                    <select
                        value={vendorId}
                        onChange={(e) => setVendorId(e.target.value)}
                        className="w-full rounded-md border bg-background px-2 py-2 text-sm"
                    >
                        <option value="">— select vendor —</option>
                        {vendors?.map((v) => (
                            <option key={v.id} value={v.id}>
                                {v.vendor_no} · {v.registered_name}
                                {v.is_vat_registered ? ' · VAT' : ''}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <FieldLabel>Vendor invoice #</FieldLabel>
                    <input
                        type="text"
                        value={vendorInvoiceNo}
                        onChange={(e) => setVendorInvoiceNo(e.target.value)}
                        className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                    />
                </div>
                <div>
                    <FieldLabel>Bill date</FieldLabel>
                    <input
                        type="date"
                        value={billDate}
                        onChange={(e) => setBillDate(e.target.value)}
                        className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                    />
                </div>
            </div>

            <div className="mt-3 grid grid-cols-3 gap-4">
                <div>
                    <FieldLabel>AP account</FieldLabel>
                    <AccountPicker value={apAccountId} onChange={setApAccountId} />
                </div>
                <div>
                    <FieldLabel>VAT input account</FieldLabel>
                    <AccountPicker value={vatInputAccountId} onChange={setVatInputAccountId} />
                </div>
                <div>
                    <FieldLabel>WHT payable account</FieldLabel>
                    <AccountPicker value={whtPayableAccountId} onChange={setWhtPayableAccountId} />
                </div>
            </div>

            <div className="mt-4 overflow-hidden rounded-lg border bg-card">
                <table className="w-full text-sm">
                    <thead className="bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th className="w-12 px-2 py-2 text-right">#</th>
                            <th className="px-2 py-2">Description</th>
                            <th className="w-32 px-2 py-2">Expense Account</th>
                            <th className="w-24 px-2 py-2">ATC</th>
                            <th className="w-32 px-2 py-2 text-right">Amount</th>
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
                                    <AccountPicker
                                        value={l.expense_account_id}
                                        onChange={(id) => updateLine(i, { expense_account_id: id })}
                                        filter={(a) => a.type === 'expense'}
                                    />
                                </td>
                                <td className="px-2 py-1">
                                    <input
                                        type="text"
                                        value={l.atc_code}
                                        onChange={(e) => updateLine(i, { atc_code: e.target.value.toUpperCase() })}
                                        placeholder="WI010"
                                        className="w-full rounded-md border bg-background px-2 py-1 font-mono text-xs"
                                    />
                                </td>
                                <td className="px-2 py-1">
                                    <MoneyInput
                                        value={l.amount}
                                        onChange={(v) => updateLine(i, { amount: v })}
                                    />
                                </td>
                                <td className="px-2 py-1 text-right">
                                    <button
                                        type="button"
                                        onClick={() => setLines((p) => (p.length <= 1 ? p : p.filter((_, idx) => idx !== i)))}
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
                onClick={() => setLines((p) => [...p, blankLine(vendor?.default_atc_code ?? '')])}
                className="mt-3 rounded-md border border-dashed px-3 py-1.5 text-xs text-muted-foreground hover:bg-accent"
            >
                + Add line
            </button>

            <div className="ml-auto mt-4 w-full max-w-sm rounded-lg border bg-card p-4 text-sm">
                <dl className="space-y-1 tabular-nums">
                    <Row label="Subtotal" value={formatPhp(fromScaled(subtotal, 2))} />
                    {vendor?.is_vat_registered && (
                        <Row label="VAT input (12%)" value={formatPhp(fromScaled(vat, 2))} />
                    )}
                    <Row label="Less: Withholding" value={`(${formatPhp(fromScaled(wht, 2))})`} muted />
                    <Row
                        label="Net payable"
                        value={formatPhp(fromScaled(subtotal + vat - wht, 2))}
                        bold
                    />
                </dl>
                <p className="mt-2 text-[10px] text-muted-foreground">
                    Preview only — server-side WHT rates per ATC are authoritative.
                </p>
            </div>

            {post.isError && (
                <div className="mt-4 rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                    {(post.error as { response?: { data?: { message?: string } } })?.response?.data
                        ?.message ?? 'Post failed.'}
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
