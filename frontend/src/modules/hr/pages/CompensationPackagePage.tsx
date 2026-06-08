import { useState } from 'react';

import {
    useCloseCompensationPackage,
    useCompensationPackages,
    useCreateCompensationPackage,
    type CompensationPackageInput,
} from '@/modules/payroll/api/compensation-packages';
import { useEmployee } from '../api/employees';

interface Props {
    employeeId: string;
}

const EMPTY: Omit<CompensationPackageInput, 'employee_id'> = {
    effective_from: new Date().toISOString().slice(0, 10),
    effective_to: '',
    basic_monthly: '',
    working_days_per_month: 26,
    hours_per_day: 8,
    is_minimum_wage_earner: false,
};

export function CompensationPackagePage({ employeeId }: Props) {
    const { data: employee } = useEmployee(employeeId);
    const { data: packages, isLoading } = useCompensationPackages(employeeId);
    const create = useCreateCompensationPackage();
    const close  = useCloseCompensationPackage();

    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState(EMPTY);

    const set = <K extends keyof typeof EMPTY>(key: K, value: (typeof EMPTY)[K]) =>
        setForm((f) => ({ ...f, [key]: value }));

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        create.mutate(
            {
                employee_id:            employeeId,
                effective_from:         form.effective_from,
                effective_to:           form.effective_to || undefined,
                basic_monthly:          form.basic_monthly,
                working_days_per_month: form.working_days_per_month,
                hours_per_day:          form.hours_per_day,
                is_minimum_wage_earner: form.is_minimum_wage_earner,
            },
            {
                onSuccess: () => {
                    setShowForm(false);
                    setForm(EMPTY);
                },
            },
        );
    };

    return (
        <div className="container max-w-3xl py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Compensation — {employee?.full_name ?? '…'}
                    </h1>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Active package is used by <code className="rounded bg-muted px-1 font-mono">ComputePayrollRun</code>.
                        Create a new package to supersede the current one.
                    </p>
                </div>
                <div className="flex gap-2 text-sm">
                    <a
                        href={`#/hr/employees/${employeeId}/edit`}
                        className="rounded-md border px-3 py-1.5 hover:bg-accent"
                    >
                        ← Employee
                    </a>
                    <button
                        type="button"
                        onClick={() => setShowForm((v) => !v)}
                        className="rounded-md bg-primary px-3 py-1.5 font-medium text-primary-foreground hover:opacity-90"
                    >
                        {showForm ? 'Cancel' : '+ New package'}
                    </button>
                </div>
            </header>

            {showForm && (
                <form
                    onSubmit={submit}
                    className="mt-6 rounded-lg border bg-card p-5 space-y-4"
                >
                    <h2 className="text-sm font-medium uppercase tracking-wide text-muted-foreground">
                        New compensation package
                    </h2>

                    <div className="grid grid-cols-2 gap-4">
                        <Field label="Effective from" required>
                            <input
                                type="date"
                                value={form.effective_from}
                                onChange={(e) => set('effective_from', e.target.value)}
                                required
                                className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                            />
                        </Field>
                        <Field label="Effective to (leave blank = open-ended)">
                            <input
                                type="date"
                                value={form.effective_to ?? ''}
                                onChange={(e) => set('effective_to', e.target.value)}
                                className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                            />
                        </Field>
                    </div>

                    <div className="grid grid-cols-3 gap-4">
                        <Field label="Basic monthly (PHP)" required>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                value={form.basic_monthly}
                                onChange={(e) => set('basic_monthly', e.target.value)}
                                required
                                placeholder="30000.00"
                                className="w-full rounded-md border bg-background px-3 py-2 text-sm font-mono"
                            />
                        </Field>
                        <Field label="Working days / month">
                            <input
                                type="number"
                                min="1"
                                max="31"
                                value={form.working_days_per_month ?? ''}
                                onChange={(e) => set('working_days_per_month', Number(e.target.value))}
                                className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                            />
                        </Field>
                        <Field label="Hours / day">
                            <input
                                type="number"
                                min="1"
                                max="24"
                                value={form.hours_per_day ?? ''}
                                onChange={(e) => set('hours_per_day', Number(e.target.value))}
                                className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                            />
                        </Field>
                    </div>

                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.is_minimum_wage_earner ?? false}
                            onChange={(e) => set('is_minimum_wage_earner', e.target.checked)}
                            className="rounded"
                        />
                        Minimum Wage Earner (MWE) — exempt from income tax per RA 9504
                    </label>

                    {create.isError && (
                        <div className="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                            {(create.error as { response?: { data?: { message?: string } } })
                                ?.response?.data?.message ?? 'Save failed.'}
                        </div>
                    )}

                    <div className="flex justify-end gap-2">
                        <button
                            type="button"
                            onClick={() => setShowForm(false)}
                            className="rounded-md border px-3 py-1.5 text-sm hover:bg-accent"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={!form.basic_monthly || !form.effective_from || create.isPending}
                            className="rounded-md bg-primary px-4 py-1.5 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                        >
                            {create.isPending ? 'Saving…' : 'Save package'}
                        </button>
                    </div>
                </form>
            )}

            <div className="mt-6">
                {isLoading && (
                    <p className="text-sm text-muted-foreground">Loading…</p>
                )}
                {!isLoading && (!packages || packages.length === 0) && (
                    <div className="rounded-lg border border-dashed bg-muted/30 p-6 text-center text-sm text-muted-foreground">
                        No compensation packages yet. Add one to enable payroll computation for this employee.
                    </div>
                )}
                <div className="space-y-3">
                    {(packages ?? []).map((pkg) => {
                        const isActive = !pkg.effective_to || pkg.effective_to >= new Date().toISOString().slice(0, 10);
                        return (
                            <div
                                key={pkg.id}
                                className={`rounded-lg border p-4 ${isActive ? 'border-primary/30 bg-primary/5' : 'bg-card opacity-70'}`}
                            >
                                <div className="flex items-start justify-between">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="font-mono text-sm font-semibold">
                                                ₱{Number(pkg.basic_monthly).toLocaleString('en-PH', { minimumFractionDigits: 2 })} / mo
                                            </span>
                                            {isActive && (
                                                <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-medium text-emerald-800">
                                                    Active
                                                </span>
                                            )}
                                            {pkg.is_minimum_wage_earner && (
                                                <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-800">
                                                    MWE
                                                </span>
                                            )}
                                        </div>
                                        <div className="mt-1 text-xs text-muted-foreground">
                                            {pkg.effective_from} — {pkg.effective_to ?? 'open-ended'}
                                            {pkg.basic_daily && ` · ₱${pkg.basic_daily}/day`}
                                            {pkg.working_days_per_month && ` · ${pkg.working_days_per_month}d/mo`}
                                            {pkg.hours_per_day && ` · ${pkg.hours_per_day}h/day`}
                                        </div>
                                    </div>
                                    {isActive && (
                                        <button
                                            type="button"
                                            onClick={() => {
                                                if (confirm('Close out this compensation package?')) {
                                                    close.mutate(pkg.id);
                                                }
                                            }}
                                            disabled={close.isPending}
                                            className="rounded-md border px-2 py-0.5 text-xs text-destructive hover:bg-destructive/10 disabled:opacity-50"
                                        >
                                            Close out
                                        </button>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}

function Field({ label, required, children }: { label: string; required?: boolean; children: React.ReactNode }) {
    return (
        <div className="space-y-1">
            <label className="block text-xs font-medium uppercase tracking-wide text-muted-foreground">
                {label}
                {required && <span className="text-destructive"> *</span>}
            </label>
            {children}
        </div>
    );
}
