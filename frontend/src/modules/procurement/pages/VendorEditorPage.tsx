import { useEffect, useState } from 'react';

import { useCreateVendor, useVendor, type VendorInput } from '../api/vendors';

interface Props {
    mode: 'new' | 'edit';
    vendorId?: string;
}

const COMMON_ATC: { code: string; label: string }[] = [
    { code: 'WC010', label: 'WC010 — Professional / talent fees (10%)' },
    { code: 'WC020', label: 'WC020 — Professional / talent fees EWT (15%)' },
    { code: 'WC100', label: 'WC100 — Income payments to corporations (15%)' },
    { code: 'WI010', label: 'WI010 — Goods manufacturing (1%)' },
    { code: 'WI011', label: 'WI011 — Goods (TWA 2%)' },
    { code: 'WI070', label: 'WI070 — Services (2%)' },
    { code: 'WI080', label: 'WI080 — Services (TWA 1%)' },
];

const EMPTY: VendorInput = {
    registered_name: '',
    tin: '',
    is_vat_registered: false,
    is_government_supplier: false,
    is_top_withholding_agent: false,
    default_atc_code: '',
    default_withholding_rate: '',
    payment_terms_days: 30,
    email: '',
};

export function VendorEditorPage({ mode, vendorId }: Props) {
    const { data: existing } = useVendor(mode === 'edit' ? (vendorId ?? null) : null);
    const create = useCreateVendor();

    const [form, setForm] = useState<VendorInput>(EMPTY);

    useEffect(() => {
        if (existing) {
            setForm({
                registered_name:          existing.registered_name,
                tin:                      existing.tin ?? '',
                is_vat_registered:        existing.is_vat_registered,
                is_government_supplier:   existing.is_government_supplier,
                is_top_withholding_agent: existing.is_top_withholding_agent,
                default_atc_code:         existing.default_atc_code ?? '',
                default_withholding_rate: existing.default_withholding_rate ?? '',
                payment_terms_days:       existing.payment_terms_days,
                email:                    existing.email ?? '',
            });
        }
    }, [existing]);

    const set = <K extends keyof VendorInput>(key: K, value: VendorInput[K]) =>
        setForm((f) => ({ ...f, [key]: value }));

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (mode === 'new') {
            create.mutate(form, {
                onSuccess: () => (window.location.hash = '#/procurement/vendors'),
            });
        }
    };

    return (
        <div className="container max-w-2xl py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">
                        {mode === 'new' ? 'New Vendor' : `Edit ${existing?.vendor_no ?? '…'}`}
                    </h1>
                    <p className="mt-1 text-xs text-muted-foreground">
                        ATC and withholding rate default into every vendor bill and Form 2307.
                    </p>
                </div>
                <div className="flex gap-2 text-sm">
                    <a
                        href="#/procurement/vendors"
                        className="rounded-md border px-3 py-1.5 hover:bg-accent"
                    >
                        Cancel
                    </a>
                    <button
                        type="submit"
                        form="vendor-form"
                        disabled={!form.registered_name || create.isPending}
                        className="rounded-md bg-primary px-3 py-1.5 font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                    >
                        {create.isPending ? 'Saving…' : 'Save'}
                    </button>
                </div>
            </header>

            <form id="vendor-form" onSubmit={submit} className="mt-6 space-y-4">
                <section className="rounded-lg border bg-card p-5 space-y-4">
                    <h2 className="text-sm font-medium uppercase tracking-wide text-muted-foreground">
                        Business details
                    </h2>

                    <Field
                        label="Registered name"
                        required
                        value={form.registered_name}
                        onChange={(v) => set('registered_name', v)}
                    />

                    <div className="grid grid-cols-2 gap-4">
                        <Field
                            label="TIN"
                            mono
                            value={form.tin ?? ''}
                            onChange={(v) => set('tin', v)}
                            placeholder="000-123-456-000"
                            hint="Required for VAT-registered suppliers."
                        />
                        <Field
                            label="Email"
                            type="email"
                            value={form.email ?? ''}
                            onChange={(v) => set('email', v)}
                        />
                    </div>

                    <Field
                        label="Payment terms (days)"
                        type="number"
                        value={String(form.payment_terms_days ?? 30)}
                        onChange={(v) => set('payment_terms_days', Number(v) || 0)}
                        hint="0 = cash on delivery."
                    />
                </section>

                <section className="rounded-lg border bg-card p-5 space-y-4">
                    <h2 className="text-sm font-medium uppercase tracking-wide text-muted-foreground">
                        Tax classification
                    </h2>

                    <Toggle
                        checked={!!form.is_vat_registered}
                        onChange={(v) => set('is_vat_registered', v)}
                        label="VAT-registered supplier"
                        description="Bills from this vendor include 12% input VAT claimable against output VAT."
                    />
                    <Toggle
                        checked={!!form.is_government_supplier}
                        onChange={(v) => set('is_government_supplier', v)}
                        label="Government / GOCC"
                        description="Government payments require 5% government VAT withholding per NIRC §114(C)."
                    />
                    <Toggle
                        checked={!!form.is_top_withholding_agent}
                        onChange={(v) => set('is_top_withholding_agent', v)}
                        label="Top Withholding Agent (TWA)"
                        description="RR 11-2018 — purchases from TWAs face 1% (goods) / 2% (services) EWT regardless of ATC."
                    />

                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-1">
                            <label className="block text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Default ATC
                            </label>
                            <select
                                value={form.default_atc_code ?? ''}
                                onChange={(e) => set('default_atc_code', e.target.value)}
                                className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                            >
                                <option value="">— none —</option>
                                {COMMON_ATC.map((a) => (
                                    <option key={a.code} value={a.code}>
                                        {a.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <Field
                            label="WHT rate override"
                            type="number"
                            value={form.default_withholding_rate ?? ''}
                            onChange={(v) => set('default_withholding_rate', v)}
                            placeholder="0.01"
                            hint="Override the ATC-derived rate (0–1 decimal, e.g. 0.01 = 1%)."
                        />
                    </div>
                </section>

                {mode === 'edit' && (
                    <div className="rounded-md border border-dashed bg-muted/30 p-3 text-xs text-muted-foreground">
                        Vendor edits (UpdateVendor action) are pending backend implementation.
                    </div>
                )}

                {create.isError && (
                    <div className="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                        {(create.error as { response?: { data?: { message?: string } } })?.response
                            ?.data?.message ?? 'Save failed.'}
                    </div>
                )}
            </form>
        </div>
    );
}

function Field({
    label,
    value,
    onChange,
    type = 'text',
    placeholder,
    hint,
    mono,
    required,
}: {
    label: string;
    value: string;
    onChange: (v: string) => void;
    type?: string;
    placeholder?: string;
    hint?: string;
    mono?: boolean;
    required?: boolean;
}) {
    return (
        <div className="space-y-1">
            <label className="block text-xs font-medium uppercase tracking-wide text-muted-foreground">
                {label}
                {required && <span className="text-destructive"> *</span>}
            </label>
            <input
                type={type}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                required={required}
                className={`w-full rounded-md border bg-background px-3 py-2 text-sm ${mono ? 'font-mono' : ''}`}
            />
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
        </div>
    );
}

function Toggle({
    checked,
    onChange,
    label,
    description,
}: {
    checked: boolean;
    onChange: (v: boolean) => void;
    label: string;
    description: string;
}) {
    return (
        <label className="flex items-start gap-3 rounded-md p-2 hover:bg-muted/30">
            <input
                type="checkbox"
                checked={checked}
                onChange={(e) => onChange(e.target.checked)}
                className="mt-1"
            />
            <div className="text-sm">
                <div className="font-medium">{label}</div>
                <div className="text-xs text-muted-foreground">{description}</div>
            </div>
        </label>
    );
}
