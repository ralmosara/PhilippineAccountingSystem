import { DataTable, type Column } from '@/shared/components/DataTable';
import { formatPhp } from '@/shared/lib/money';

import { useProjects, type Project } from '../api/projects';

const BILLING_LABELS: Record<Project['billing_type'], string> = {
    fixed_price:        'Fixed Price',
    time_and_materials: 'Time & Materials',
    retainer:           'Retainer',
};

const STATUS_STYLES: Record<Project['status'], string> = {
    draft:     'bg-slate-100 text-slate-700',
    active:    'bg-emerald-100 text-emerald-800',
    on_hold:   'bg-amber-100 text-amber-800',
    completed: 'bg-blue-100 text-blue-800',
    cancelled: 'bg-red-100 text-red-700',
};

export function ProjectsPage() {
    const { data: projects, isLoading } = useProjects();

    const columns: Column<Project>[] = [
        {
            key: 'code',
            header: 'Code',
            render: (p) => (
                <a href={`#/projects/${p.id}`} className="font-mono text-primary hover:underline">
                    {p.code}
                </a>
            ),
        },
        {
            key: 'name',
            header: 'Name',
            render: (p) => (
                <a href={`#/projects/${p.id}`} className="hover:underline">
                    {p.name}
                </a>
            ),
        },
        {
            key: 'billing_type',
            header: 'Billing Type',
            render: (p) => (
                <span className="rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-800">
                    {BILLING_LABELS[p.billing_type]}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            render: (p) => (
                <span className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${STATUS_STYLES[p.status]}`}>
                    {p.status.replace('_', ' ')}
                </span>
            ),
        },
        {
            key: 'contract_value',
            header: 'Contract Value',
            align: 'right',
            numeric: true,
            render: (p) => formatPhp(p.contract_value),
        },
        {
            key: 'budget_hours',
            header: 'Budget Hrs',
            align: 'right',
            numeric: true,
            render: (p) => (p.budget_hours ? p.budget_hours : '—'),
        },
    ];

    return (
        <div className="container py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">Projects</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Track project timesheets, WIP recognition, and contract values.
                    </p>
                </div>
                <a
                    href="#/projects/new"
                    className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90"
                >
                    New Project
                </a>
            </header>

            <div className="mt-6">
                <DataTable
                    columns={columns}
                    rows={projects}
                    rowKey={(p) => p.id}
                    isLoading={isLoading}
                    emptyState="No projects yet. Create one to get started."
                />
            </div>
        </div>
    );
}
