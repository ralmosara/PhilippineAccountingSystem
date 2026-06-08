import { useState } from 'react';

import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

import { useDepartments } from '../api/employees';
import { useCreatePosition, useDeactivatePosition, usePositionsList, type PositionInput } from '../api/positions';

const EMPTY: PositionInput = { code: '', title: '', department_id: '', salary_grade_min: '', salary_grade_max: '' };

export function PositionsPage() {
    const user = useAuthStore((s) => s.user);
    const canManage = hasPermission(user, 'hr.employees.manage');

    const { data: positions, isLoading } = usePositionsList();
    const { data: departments } = useDepartments();
    const create     = useCreatePosition();
    const deactivate = useDeactivatePosition();

    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState<PositionInput>(EMPTY);
    const set = <K extends keyof PositionInput>(key: K, value: PositionInput[K]) =>
        setForm((f) => ({ ...f, [key]: value }));

    const deptName = (id: string | null) =>
        id ? (departments ?? []).find((d) => d.id === id)?.name ?? id : '—';

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        create.mutate(
            {
                code:             form.code,
                title:            form.title,
                department_id:    form.department_id || undefined,
                salary_grade_min: form.salary_grade_min || undefined,
                salary_grade_max: form.salary_grade_max || undefined,
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
                    <h1 className="text-2xl font-semibold">Positions</h1>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Job positions used in employee records. Selected when creating or editing an employee.
                    </p>
                </div>
                <div className="flex gap-2 text-sm">
                    <a href="#/hr/employees" className="rounded-md border px-3 py-1.5 hover:bg-accent">
                        Employees
                    </a>
                    {canManage && (
                        <button
                            type="button"
                            onClick={() => setShowForm((v) => !v)}
                            className="rounded-md bg-primary px-3 py-1.5 font-medium text-primary-foreground hover:opacity-90"
                        >
                            {showForm ? 'Cancel' : '+ New position'}
                        </button>
                    )}
                </div>
            </header>

            {showForm && (
                <form onSubmit={submit} className="mt-6 rounded-lg border bg-card p-5 space-y-4">
                    <h2 className="text-sm font-medium uppercase tracking-wide text-muted-foreground">
                        New position
                    </h2>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-1">
                            <label className="block text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Code <span className="text-destructive">*</span>
                            </label>
                            <input
                                value={form.code}
                                onChange={(e) => set('code', e.target.value)}
                                required
                                placeholder="MGR-001"
                                className="w-full rounded-md border bg-background px-3 py-2 text-sm font-mono"
                            />
                        </div>
                        <div className="space-y-1">
                            <label className="block text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Title <span className="text-destructive">*</span>
                            </label>
                            <input
                                value={form.title}
                                onChange={(e) => set('title', e.target.value)}
                                required
                                placeholder="Operations Manager"
                                className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                            />
                        </div>
                    </div>

                    <div className="space-y-1">
                        <label className="block text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            Department (optional)
                        </label>
                        <select
                            value={form.department_id ?? ''}
                            onChange={(e) => set('department_id', e.target.value)}
                            className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                        >
                            <option value="">— any department —</option>
                            {(departments ?? []).map((d) => (
                                <option key={d.id} value={d.id}>
                                    {d.code} — {d.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-1">
                            <label className="block text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Salary grade min (PHP)
                            </label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                value={form.salary_grade_min ?? ''}
                                onChange={(e) => set('salary_grade_min', e.target.value)}
                                placeholder="20000.00"
                                className="w-full rounded-md border bg-background px-3 py-2 text-sm font-mono"
                            />
                        </div>
                        <div className="space-y-1">
                            <label className="block text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Salary grade max (PHP)
                            </label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                value={form.salary_grade_max ?? ''}
                                onChange={(e) => set('salary_grade_max', e.target.value)}
                                placeholder="50000.00"
                                className="w-full rounded-md border bg-background px-3 py-2 text-sm font-mono"
                            />
                        </div>
                    </div>

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
                            disabled={!form.code || !form.title || create.isPending}
                            className="rounded-md bg-primary px-4 py-1.5 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                        >
                            {create.isPending ? 'Saving…' : 'Save'}
                        </button>
                    </div>
                </form>
            )}

            <div className="mt-6 overflow-hidden rounded-lg border">
                {isLoading && (
                    <div className="p-4 text-sm text-muted-foreground">Loading…</div>
                )}
                {!isLoading && (!positions || positions.length === 0) && (
                    <div className="p-6 text-center text-sm text-muted-foreground">
                        No positions yet. Add one to enable position selection on employee records.
                    </div>
                )}
                {(positions ?? []).map((p, i) => (
                    <div
                        key={p.id}
                        className={`flex items-center justify-between px-4 py-3 ${i > 0 ? 'border-t' : ''} ${!p.is_active ? 'opacity-50' : ''}`}
                    >
                        <div>
                            <span className="font-mono text-xs text-muted-foreground">{p.code}</span>
                            <span className="mx-2 text-muted-foreground">·</span>
                            <span className="text-sm font-medium">{p.title}</span>
                            {p.department_id && (
                                <span className="ml-2 text-xs text-muted-foreground">({deptName(p.department_id)})</span>
                            )}
                            {p.salary_grade_min && p.salary_grade_max && (
                                <span className="ml-2 text-xs text-muted-foreground">
                                    ₱{Number(p.salary_grade_min).toLocaleString()} – ₱{Number(p.salary_grade_max).toLocaleString()}
                                </span>
                            )}
                        </div>
                        {canManage && p.is_active && (
                            <button
                                type="button"
                                onClick={() => {
                                    if (confirm(`Deactivate "${p.title}"?`)) deactivate.mutate(p.id);
                                }}
                                disabled={deactivate.isPending}
                                className="rounded-md border px-2 py-0.5 text-xs text-destructive hover:bg-destructive/10 disabled:opacity-50"
                            >
                                Deactivate
                            </button>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}
