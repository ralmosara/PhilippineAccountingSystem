import { useState } from 'react';

import { useCreateProject, useProject, useUpdateProject, type Project } from '../api/projects';

interface Props {
    projectId?: string; // undefined → create mode
}

export function ProjectEditorPage({ projectId }: Props) {
    const isEdit = !!projectId;

    const { data: existing, isLoading } = useProject(projectId ?? '');
    const create = useCreateProject();
    const update = useUpdateProject(projectId ?? '');

    const [form, setForm] = useState<Partial<Project>>({});
    const [submitted, setSubmitted] = useState(false);
    const [error, setError] = useState<string | null>(null);

    if (isEdit && isLoading) {
        return <div className="container py-8 text-sm text-muted-foreground">Loading…</div>;
    }

    const current: Partial<Project> = { ...existing, ...form };

    function set(field: keyof Project, value: string | null) {
        setForm((prev) => ({ ...prev, [field]: value }));
    }

    async function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        setError(null);
        try {
            if (isEdit) {
                await update.mutateAsync(form);
            } else {
                await create.mutateAsync(form);
            }
            setSubmitted(true);
            window.location.hash = '#/projects';
        } catch (err: unknown) {
            const msg =
                (err as { response?: { data?: { message?: string } } })?.response?.data?.message ??
                'Save failed.';
            setError(msg);
        }
    }

    const isPending = create.isPending || update.isPending;

    return (
        <div className="container max-w-2xl py-8">
            <header className="mb-6 flex items-center gap-3">
                <a href="#/projects" className="rounded-md border px-3 py-1.5 text-sm hover:bg-accent">
                    ← Back
                </a>
                <h1 className="text-2xl font-semibold">
                    {isEdit ? 'Edit Project' : 'New Project'}
                </h1>
            </header>

            {error && (
                <div className="mb-4 rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                    {error}
                </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-4 rounded-lg border bg-card p-6">
                <Field label="Project Code *">
                    <input
                        type="text"
                        maxLength={32}
                        required
                        value={current.code ?? ''}
                        onChange={(e) => set('code', e.target.value)}
                        placeholder="e.g. PROJ-001"
                        className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                    />
                </Field>

                <Field label="Project Name *">
                    <input
                        type="text"
                        required
                        value={current.name ?? ''}
                        onChange={(e) => set('name', e.target.value)}
                        placeholder="Project name"
                        className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                    />
                </Field>

                <Field label="Billing Type *">
                    <select
                        required
                        value={current.billing_type ?? ''}
                        onChange={(e) => set('billing_type', e.target.value)}
                        className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                    >
                        <option value="">Select billing type…</option>
                        <option value="fixed_price">Fixed Price</option>
                        <option value="time_and_materials">Time & Materials</option>
                        <option value="retainer">Retainer</option>
                    </select>
                </Field>

                <div className="grid grid-cols-2 gap-4">
                    <Field label="Contract Value (PHP)">
                        <input
                            type="number"
                            min="0"
                            step="0.01"
                            value={current.contract_value ?? ''}
                            onChange={(e) => set('contract_value', e.target.value || null)}
                            placeholder="0.00"
                            className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </Field>

                    <Field label="Budget Hours">
                        <input
                            type="number"
                            min="0"
                            step="0.25"
                            value={current.budget_hours ?? ''}
                            onChange={(e) => set('budget_hours', e.target.value || null)}
                            placeholder="0.00"
                            className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </Field>
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <Field label="Start Date">
                        <input
                            type="date"
                            value={current.starts_on ?? ''}
                            onChange={(e) => set('starts_on', e.target.value || null)}
                            className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </Field>

                    <Field label="End Date">
                        <input
                            type="date"
                            value={current.ends_on ?? ''}
                            onChange={(e) => set('ends_on', e.target.value || null)}
                            className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </Field>
                </div>

                <div className="flex justify-end gap-3 pt-2">
                    <a href="#/projects" className="rounded-md border px-4 py-2 text-sm hover:bg-accent">
                        Cancel
                    </a>
                    <button
                        type="submit"
                        disabled={isPending || submitted}
                        className="rounded-md bg-primary px-5 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                    >
                        {isPending ? 'Saving…' : isEdit ? 'Update Project' : 'Create Project'}
                    </button>
                </div>
            </form>
        </div>
    );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div>
            <label className="mb-1 block text-xs font-medium uppercase tracking-wide text-muted-foreground">
                {label}
            </label>
            {children}
        </div>
    );
}
