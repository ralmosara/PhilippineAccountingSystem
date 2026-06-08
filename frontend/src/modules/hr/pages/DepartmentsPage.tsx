import { useState } from 'react';

import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

import {
    useCreateDepartment,
    useDeactivateDepartment,
    useDepartments,
    type Department,
} from '../api/employees';

export function DepartmentsPage() {
    const user = useAuthStore((s) => s.user);
    const canManage = hasPermission(user, 'hr.employees.manage');

    const { data: departments, isLoading } = useDepartments();
    const create     = useCreateDepartment();
    const deactivate = useDeactivateDepartment();

    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState({ code: '', name: '', parent_id: '' });
    const set = (key: keyof typeof form, value: string) =>
        setForm((f) => ({ ...f, [key]: value }));

    const parentName = (id: string | null) =>
        id ? (departments ?? []).find((d) => d.id === id)?.name ?? '—' : null;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        create.mutate(
            {
                code:      form.code,
                name:      form.name,
                parent_id: form.parent_id || undefined,
            },
            {
                onSuccess: () => {
                    setShowForm(false);
                    setForm({ code: '', name: '', parent_id: '' });
                },
            },
        );
    };

    return (
        <div className="container max-w-3xl py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">Departments</h1>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Organizational units. Employees and positions are linked to departments.
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
                            {showForm ? 'Cancel' : '+ New department'}
                        </button>
                    )}
                </div>
            </header>

            {showForm && (
                <form onSubmit={submit} className="mt-6 rounded-lg border bg-card p-5 space-y-4">
                    <h2 className="text-sm font-medium uppercase tracking-wide text-muted-foreground">
                        New department
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
                                placeholder="OPS"
                                maxLength={20}
                                className="w-full rounded-md border bg-background px-3 py-2 text-sm font-mono"
                            />
                        </div>
                        <div className="space-y-1">
                            <label className="block text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Name <span className="text-destructive">*</span>
                            </label>
                            <input
                                value={form.name}
                                onChange={(e) => set('name', e.target.value)}
                                required
                                placeholder="Operations"
                                className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                            />
                        </div>
                    </div>

                    <div className="space-y-1">
                        <label className="block text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            Parent department (optional)
                        </label>
                        <select
                            value={form.parent_id}
                            onChange={(e) => set('parent_id', e.target.value)}
                            className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                        >
                            <option value="">— top-level —</option>
                            {(departments ?? []).map((d) => (
                                <option key={d.id} value={d.id}>
                                    {d.code} — {d.name}
                                </option>
                            ))}
                        </select>
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
                            disabled={!form.code || !form.name || create.isPending}
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
                {!isLoading && (!departments || departments.length === 0) && (
                    <div className="p-6 text-center text-sm text-muted-foreground">
                        No departments yet. Add one to start organizing employees.
                    </div>
                )}
                {(departments ?? []).map((d, i) => (
                    <DeptRow
                        key={d.id}
                        dept={d}
                        parentName={parentName(d.parent_id ?? null)}
                        border={i > 0}
                        canManage={canManage}
                        onDeactivate={() => {
                            if (confirm(`Deactivate "${d.name}"?`)) deactivate.mutate(d.id);
                        }}
                        isPending={deactivate.isPending}
                    />
                ))}
            </div>
        </div>
    );
}

function DeptRow({
    dept,
    parentName,
    border,
    canManage,
    onDeactivate,
    isPending,
}: {
    dept: Department;
    parentName: string | null;
    border: boolean;
    canManage: boolean;
    onDeactivate: () => void;
    isPending: boolean;
}) {
    return (
        <div
            className={`flex items-center justify-between px-4 py-3 ${border ? 'border-t' : ''}`}
        >
            <div>
                <span className="font-mono text-xs text-muted-foreground">{dept.code}</span>
                <span className="mx-2 text-muted-foreground">·</span>
                <span className="text-sm font-medium">{dept.name}</span>
                {parentName && (
                    <span className="ml-2 text-xs text-muted-foreground">
                        ↳ {parentName}
                    </span>
                )}
            </div>
            {canManage && (
                <button
                    type="button"
                    onClick={onDeactivate}
                    disabled={isPending}
                    className="rounded-md border px-2 py-0.5 text-xs text-destructive hover:bg-destructive/10 disabled:opacity-50"
                >
                    Deactivate
                </button>
            )}
        </div>
    );
}
