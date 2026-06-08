import { useState } from 'react';

import { useSubmitLeaveRequest, type LeaveRequestInput } from '../api/leave-requests';

const LEAVE_TYPES: { value: LeaveRequestInput['leave_type']; label: string }[] = [
    { value: 'sick',        label: 'Sick Leave' },
    { value: 'vacation',    label: 'Vacation Leave' },
    { value: 'emergency',   label: 'Emergency Leave' },
    { value: 'maternity',   label: 'Maternity Leave' },
    { value: 'paternity',   label: 'Paternity Leave' },
    { value: 'solo_parent', label: 'Solo Parent Leave' },
    { value: 'bereavement', label: 'Bereavement Leave' },
];

const EMPTY: LeaveRequestInput = {
    employee_id: '',
    leave_type:  'vacation',
    start_date:  new Date().toISOString().slice(0, 10),
    end_date:    new Date().toISOString().slice(0, 10),
    reason:      '',
};

export function SubmitLeaveRequestPage() {
    const [form, setForm] = useState<LeaveRequestInput>(EMPTY);
    const submit = useSubmitLeaveRequest();

    const set = <K extends keyof LeaveRequestInput>(key: K, value: LeaveRequestInput[K]) =>
        setForm((f) => ({ ...f, [key]: value }));

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        submit.mutate(
            { ...form, reason: form.reason || undefined },
            { onSuccess: () => (window.location.hash = '#/hr/leave-requests') },
        );
    };

    const isValid =
        form.employee_id.trim() !== '' &&
        form.leave_type !== '' &&
        form.start_date !== '' &&
        form.end_date !== '' &&
        form.end_date >= form.start_date;

    return (
        <div className="container max-w-2xl py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">Submit Leave Request</h1>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Business days (Mon–Fri) between the selected dates will be calculated
                        automatically.
                    </p>
                </div>
                <div className="flex gap-2 text-sm">
                    <a
                        href="#/hr/leave-requests"
                        className="rounded-md border px-3 py-1.5 hover:bg-accent"
                    >
                        Cancel
                    </a>
                    <button
                        type="submit"
                        form="leave-request-form"
                        disabled={!isValid || submit.isPending}
                        className="rounded-md bg-primary px-3 py-1.5 font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                    >
                        {submit.isPending ? 'Submitting…' : 'Submit'}
                    </button>
                </div>
            </header>

            <form id="leave-request-form" onSubmit={handleSubmit} className="mt-6 space-y-4">
                <section className="rounded-lg border bg-card p-5 space-y-4">
                    <h2 className="text-sm font-medium uppercase tracking-wide text-muted-foreground">
                        Leave details
                    </h2>

                    <Field
                        label="Employee ID"
                        required
                        value={form.employee_id}
                        onChange={(v) => set('employee_id', v)}
                        placeholder="UUID of the employee"
                        mono
                    />

                    <div className="space-y-1">
                        <label className="block text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            Leave type <span className="text-destructive">*</span>
                        </label>
                        <select
                            value={form.leave_type}
                            onChange={(e) =>
                                set('leave_type', e.target.value as LeaveRequestInput['leave_type'])
                            }
                            required
                            className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                        >
                            {LEAVE_TYPES.map((t) => (
                                <option key={t.value} value={t.value}>
                                    {t.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <Field
                            label="Start date"
                            type="date"
                            required
                            value={form.start_date}
                            onChange={(v) => set('start_date', v)}
                        />
                        <Field
                            label="End date"
                            type="date"
                            required
                            value={form.end_date}
                            onChange={(v) => set('end_date', v)}
                        />
                    </div>

                    {form.start_date && form.end_date && form.end_date < form.start_date && (
                        <p className="text-xs text-destructive">
                            End date must be on or after the start date.
                        </p>
                    )}

                    <div className="space-y-1">
                        <label className="block text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            Reason (optional)
                        </label>
                        <textarea
                            value={form.reason ?? ''}
                            onChange={(e) => set('reason', e.target.value)}
                            rows={4}
                            placeholder="Brief description of the reason for leave…"
                            className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </div>
                </section>

                {submit.error && (
                    <div className="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                        {(submit.error as { response?: { data?: { message?: string } } })?.response
                            ?.data?.message ?? 'Submission failed. Please check your inputs.'}
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
