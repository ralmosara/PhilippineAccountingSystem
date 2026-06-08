import { useState } from 'react';

import { DataTable, type Column } from '@/shared/components/DataTable';

import { useCustomers, type Customer } from '../api/sales-invoices';

export function CustomersPage() {
    const [query, setQuery] = useState('');
    const { data: customers, isLoading } = useCustomers(query);

    const columns: Column<Customer>[] = [
        {
            key: 'customer_no',
            header: 'No',
            render: (c) => (
                <a
                    href={`#/sales/customers/${c.id}/detail`}
                    className="font-mono text-primary hover:underline"
                >
                    {c.customer_no}
                </a>
            ),
        },
        { key: 'registered_name', header: 'Registered Name' },
        {
            key: 'tin',
            header: 'TIN',
            render: (c) => <span className="font-mono text-xs">{c.tin ?? '—'}</span>,
        },
        {
            key: 'class',
            header: 'Class',
            render: (c) => (
                <div className="flex flex-wrap gap-1">
                    {c.is_government && <Chip tone="purple">Gov</Chip>}
                    {c.is_senior_citizen && <Chip tone="amber">Senior</Chip>}
                    {c.is_pwd && <Chip tone="amber">PWD</Chip>}
                    {c.is_vat_registered && <Chip tone="slate">VAT</Chip>}
                </div>
            ),
        },
        {
            key: 'terms',
            header: 'Terms',
            align: 'right',
            numeric: true,
            render: (c) => (c.payment_terms_days ? `${c.payment_terms_days}d` : '—'),
        },
    ];

    return (
        <div className="container py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">Customers</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Customer master. Class flags drive VAT computation at SI issuance.
                    </p>
                </div>

                <div className="flex items-center gap-2 text-sm">
                    <input
                        type="search"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search by no / name / TIN"
                        className="rounded-md border bg-background px-2 py-1"
                    />
                    <a
                        href="#/sales/customers/new"
                        className="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:opacity-90"
                    >
                        + New customer
                    </a>
                </div>
            </header>

            <div className="mt-6">
                <DataTable
                    columns={columns}
                    rows={customers}
                    rowKey={(c) => c.id}
                    isLoading={isLoading}
                    emptyState="No customers match the current search."
                />
            </div>
        </div>
    );
}

function Chip({ children, tone }: { children: React.ReactNode; tone: 'purple' | 'amber' | 'slate' }) {
    const colour =
        tone === 'purple' ? 'bg-purple-100 text-purple-800'
        : tone === 'amber' ? 'bg-amber-100 text-amber-800'
        : 'bg-slate-100 text-slate-700';
    return <span className={`rounded-full px-1.5 py-0.5 text-[10px] font-medium ${colour}`}>{children}</span>;
}
