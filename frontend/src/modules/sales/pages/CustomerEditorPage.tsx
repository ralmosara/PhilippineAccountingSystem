import { useEffect, useState } from 'react';

import { useCustomer, useSaveCustomer, type CustomerInput } from '../api/customers';

/**
 * Create or edit a customer. Class flags map 1:1 to backend booleans —
 * senior/PWD/government are mutually exclusive in practice but the UI
 * doesn't hard-enforce that (a senior PWD government employee buying
 * for personal use is technically the same person; flag whichever applies
 * to THIS sale relationship).
 */
interface Props {
    mode: 'new' | 'edit';
    customerId?: string;
}

export function CustomerEditorPage({ mode, customerId }: Props) {
    const { data: existing } = useCustomer(mode === 'edit' ? (customerId ?? null) : null);
    const save = useSaveCustomer();

    const [form, setForm] = useState<CustomerInput>({
        registered_name: '',
        tin: '',
        is_vat_registered: false,
        is_government: false,
        is_senior_citizen: false,
        is_pwd: false,
        email: '',
        payment_terms_days: 30,
    });

    useEffect(() => {
        if (existing) {
            setForm({
                registered_name: existing.registered_name,
                tin: existing.tin ?? '',
                is_vat_registered: existing.is_vat_registered,
                is_government: existing.is_government,
                is_senior_citizen: existing.is_senior_citizen,
                is_pwd: existing.is_pwd,
                email: existing.email ?? '',
                payment_terms_days: existing.payment_terms_days,
            });
        }
    }, [existing]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        save.mutate(
            { id: mode === 'edit' ? customerId : undefined, input: form },
            { onSuccess: () => (window.location.hash = '#/sales/customers') },
        );
    };

    return (
        <div className="container max-w-2xl py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">
                        {mode === 'new' ? 'New customer' : `Edit ${existing?.customer_no ?? ''}`}
                    </h1>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Class flags determine VAT treatment on every SI to this customer.
                    </p>
                </div>
                <div className="flex gap-2 text-sm">
                    <a href="#/sales/customers" className="rounded-md border px-3 py-1.5 hover:bg-accent">
                        Cancel
                    </a>
                    <button
                        type="submit"
                        form="customer-form"
                        disabled={!form.registered_name || save.isPending}
                        className="rounded-md bg-primary px-3 py-1.5 font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                    >
                        {save.isPending ? 'Saving…' : 'Save'}
                    </button>
                </div>
            </header>

            <form id="customer-form" onSubmit={submit} className="mt-6 space-y-4 rounded-lg border bg-card p-6">
                <Field
                    label="Registered name"
                    required
                    value={form.registered_name}
                    onChange={(v) => setForm((f) => ({ ...f, registered_name: v }))}
                />

                <div className="grid grid-cols-2 gap-4">
                    <Field
                        label="TIN"
                        value={form.tin ?? ''}
                        onChange={(v) => setForm((f) => ({ ...f, tin: v }))}
                        placeholder="000-123-456-000"
                        hint="9 or 12 digits; dashes optional."
                        mono
                    />
                    <Field
                        label="Email"
                        type="email"
                        value={form.email ?? ''}
                        onChange={(v) => setForm((f) => ({ ...f, email: v }))}
                    />
                </div>

                <Field
                    label="Payment terms (days)"
                    type="number"
                    value={String(form.payment_terms_days ?? 0)}
                    onChange={(v) => setForm((f) => ({ ...f, payment_terms_days: Number(v) || 0 }))}
                    hint="Used to default the SI due_date. 0 = cash on issue."
                />

                <fieldset className="space-y-2 rounded-md border p-3">
                    <legend className="px-1 text-xs uppercase tracking-wide text-muted-foreground">
                        Tax class flags
                    </legend>
                    <Toggle
                        checked={!!form.is_vat_registered}
                        onChange={(v) => setForm((f) => ({ ...f, is_vat_registered: v }))}
                        label="VAT-registered"
                        description="Sales to this customer use standard 12% output VAT classification."
                    />
                    <Toggle
                        checked={!!form.is_government}
                        onChange={(v) => setForm((f) => ({ ...f, is_government: v }))}
                        label="Government / GOCC"
                        description="BIR mandates 5% withheld VAT on sales to this customer (deducts from collectible)."
                    />
                    <Toggle
                        checked={!!form.is_senior_citizen}
                        onChange={(v) => setForm((f) => ({ ...f, is_senior_citizen: v }))}
                        label="Senior citizen (RA 9994)"
                        description="20% statutory discount + VAT-exempt classification on all lines."
                    />
                    <Toggle
                        checked={!!form.is_pwd}
                        onChange={(v) => setForm((f) => ({ ...f, is_pwd: v }))}
                        label="PWD (RA 10754)"
                        description="20% statutory discount + VAT-exempt classification on all lines."
                    />
                </fieldset>

                {save.isError && (
                    <div className="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                        {(save.error as { response?: { data?: { message?: string } } })?.response?.data
                            ?.message ?? 'Save failed.'}
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
