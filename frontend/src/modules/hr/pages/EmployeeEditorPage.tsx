import { useEffect, useState } from 'react';

import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

import { useCreateEmployee, useDepartments, useEmployee, usePositions, type EmployeeInput } from '../api/employees';

interface Props {
    mode: 'new' | 'edit';
    employeeId?: string;
}

const EMPTY: EmployeeInput = {
    first_name: '',
    last_name: '',
    middle_name: '',
    email: '',
    hired_on: new Date().toISOString().slice(0, 10),
    employment_status: 'probationary',
    department_id: '',
    position_id: '',
    tin: '',
    sss_no: '',
    philhealth_no: '',
    pagibig_no: '',
};

export function EmployeeEditorPage({ mode, employeeId }: Props) {
    const user = useAuthStore((s) => s.user);
    const canSeePii = hasPermission(user, 'hr.employees.manage');

    const { data: existing } = useEmployee(mode === 'edit' ? (employeeId ?? null) : null);
    const { data: departments } = useDepartments();
    const { data: positions } = usePositions(form.department_id || undefined);
    const create = useCreateEmployee();

    const [form, setForm] = useState<EmployeeInput>(EMPTY);

    useEffect(() => {
        if (existing) {
            setForm({
                first_name:        existing.first_name,
                last_name:         existing.last_name,
                middle_name:       existing.middle_name ?? '',
                email:             existing.email ?? '',
                hired_on:          existing.hired_on ?? EMPTY.hired_on,
                employment_status: existing.employment_status,
                department_id:     existing.department_id ?? '',
                position_id:       existing.position_id ?? '',
                tin:               existing.tin ?? '',
                sss_no:            existing.sss_no ?? '',
                philhealth_no:     existing.philhealth_no ?? '',
                pagibig_no:        existing.pagibig_no ?? '',
            });
        }
    }, [existing]);

    const set = <K extends keyof EmployeeInput>(key: K, value: EmployeeInput[K]) =>
        setForm((f) => ({ ...f, [key]: value }));

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (mode === 'new') {
            create.mutate(form, {
                onSuccess: () => (window.location.hash = '#/hr/employees'),
            });
        }
        // update is 501 on backend — show info for now
    };

    const isPending = create.isPending;
    const error     = create.error;

    return (
        <div className="container max-w-2xl py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">
                        {mode === 'new' ? 'New Employee' : `Edit ${existing?.employee_no ?? '…'}`}
                    </h1>
                    <p className="mt-1 text-xs text-muted-foreground">
                        PII fields (TIN, SSS, PhilHealth, Pag-IBIG) require{' '}
                        <code className="rounded bg-muted px-1 py-0.5 font-mono">
                            hr.employees.manage
                        </code>{' '}
                        permission.
                    </p>
                </div>
                <div className="flex gap-2 text-sm">
                    <a
                        href="#/hr/employees"
                        className="rounded-md border px-3 py-1.5 hover:bg-accent"
                    >
                        Cancel
                    </a>
                    {mode === 'edit' && employeeId && (
                        <a
                            href={`#/hr/employees/${employeeId}/compensation`}
                            className="rounded-md border bg-amber-50 px-3 py-1.5 text-amber-800 hover:bg-amber-100"
                        >
                            Compensation
                        </a>
                    )}
                    <button
                        type="submit"
                        form="employee-form"
                        disabled={!form.first_name || !form.last_name || !form.hired_on || isPending}
                        className="rounded-md bg-primary px-3 py-1.5 font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                    >
                        {isPending ? 'Saving…' : 'Save'}
                    </button>
                </div>
            </header>

            <form id="employee-form" onSubmit={submit} className="mt-6 space-y-4">
                <section className="rounded-lg border bg-card p-5 space-y-4">
                    <h2 className="text-sm font-medium uppercase tracking-wide text-muted-foreground">
                        Personal details
                    </h2>

                    <div className="grid grid-cols-3 gap-4">
                        <Field
                            label="First name"
                            required
                            value={form.first_name}
                            onChange={(v) => set('first_name', v)}
                        />
                        <Field
                            label="Middle name"
                            value={form.middle_name ?? ''}
                            onChange={(v) => set('middle_name', v)}
                        />
                        <Field
                            label="Last name"
                            required
                            value={form.last_name}
                            onChange={(v) => set('last_name', v)}
                        />
                    </div>

                    <Field
                        label="Email"
                        type="email"
                        value={form.email ?? ''}
                        onChange={(v) => set('email', v)}
                    />
                </section>

                <section className="rounded-lg border bg-card p-5 space-y-4">
                    <h2 className="text-sm font-medium uppercase tracking-wide text-muted-foreground">
                        Employment
                    </h2>

                    <div className="grid grid-cols-2 gap-4">
                        <Field
                            label="Hired on"
                            type="date"
                            required
                            value={form.hired_on}
                            onChange={(v) => set('hired_on', v)}
                        />
                        <div className="space-y-1">
                            <label className="block text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Employment status
                            </label>
                            <select
                                value={form.employment_status ?? ''}
                                onChange={(e) => set('employment_status', e.target.value)}
                                className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                            >
                                <option value="probationary">Probationary</option>
                                <option value="regular">Regular</option>
                                <option value="contract">Contract</option>
                                <option value="project">Project-based</option>
                                <option value="consultant">Consultant</option>
                            </select>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-1">
                            <label className="block text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Department
                            </label>
                            <select
                                value={form.department_id ?? ''}
                                onChange={(e) => {
                                    set('department_id', e.target.value);
                                    set('position_id', '');
                                }}
                                className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                            >
                                <option value="">— none —</option>
                                {(departments ?? []).map((d) => (
                                    <option key={d.id} value={d.id}>
                                        {d.code} — {d.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="space-y-1">
                            <label className="block text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Position
                            </label>
                            <select
                                value={form.position_id ?? ''}
                                onChange={(e) => set('position_id', e.target.value)}
                                className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                            >
                                <option value="">— none —</option>
                                {(positions ?? []).map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.code} — {p.title}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                </section>

                {canSeePii && (
                    <section className="rounded-lg border border-amber-200 bg-amber-50/40 p-5 space-y-4">
                        <h2 className="text-sm font-medium uppercase tracking-wide text-amber-700">
                            Statutory IDs (PII — HR-manage only)
                        </h2>
                        <p className="text-xs text-amber-700">
                            Stored encrypted at rest (pgcrypto). Non-HR roles see masked values
                            (e.g. ****5678).
                        </p>

                        <div className="grid grid-cols-2 gap-4">
                            <Field
                                label="TIN"
                                mono
                                value={form.tin ?? ''}
                                onChange={(v) => set('tin', v)}
                                placeholder="000-123-456-000"
                            />
                            <Field
                                label="SSS No."
                                mono
                                value={form.sss_no ?? ''}
                                onChange={(v) => set('sss_no', v)}
                                placeholder="34-1234567-8"
                            />
                            <Field
                                label="PhilHealth No."
                                mono
                                value={form.philhealth_no ?? ''}
                                onChange={(v) => set('philhealth_no', v)}
                                placeholder="12-345678901-2"
                            />
                            <Field
                                label="Pag-IBIG MID"
                                mono
                                value={form.pagibig_no ?? ''}
                                onChange={(v) => set('pagibig_no', v)}
                                placeholder="1234-5678-9012"
                            />
                        </div>
                    </section>
                )}

                {mode === 'edit' && (
                    <div className="rounded-md border border-dashed bg-muted/30 p-3 text-xs text-muted-foreground">
                        Employee edits (UpdateEmployee action) are pending backend implementation.
                        Create a new employee record for now.
                    </div>
                )}

                {error && (
                    <div className="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                        {(error as { response?: { data?: { message?: string } } })?.response?.data
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
    mono,
    required,
}: {
    label: string;
    value: string;
    onChange: (v: string) => void;
    type?: string;
    placeholder?: string;
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
        </div>
    );
}
