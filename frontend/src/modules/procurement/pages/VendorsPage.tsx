import { useState } from 'react';

import { DataTable, type Column } from '@/shared/components/DataTable';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

import { useVendors, type Vendor } from '../api/vendors';

export function VendorsPage() {
    const user = useAuthStore((s) => s.user);
    const canCreate = hasPermission(user, 'procurement.bills.create');

    const [search, setSearch] = useState('');
    const [activeOnly, setActiveOnly] = useState(true);

    const { data: vendors, isLoading } = useVendors({
        search: search || undefined,
        active_only: activeOnly,
    });

    const columns: Column<Vendor>[] = [
        {
            key: 'vendor_no',
            header: 'Vendor #',
            render: (v) => (
                <a
                    href={`#/procurement/vendors/${v.id}/edit`}
                    className="font-mono text-primary hover:underline"
                >
                    {v.vendor_no}
                </a>
            ),
        },
        { key: 'registered_name', header: 'Registered Name' },
        {
            key: 'tin',
            header: 'TIN',
            render: (v) => (
                <span className="font-mono text-sm">
                    {v.tin ?? <span className="text-muted-foreground">—</span>}
                </span>
            ),
        },
        {
            key: 'atc',
            header: 'Default ATC',
            render: (v) => v.default_atc_code ?? <span className="text-muted-foreground">—</span>,
        },
        {
            key: 'terms',
            header: 'Terms',
            align: 'right',
            numeric: true,
            render: (v) => `${v.payment_terms_days}d`,
        },
        {
            key: 'flags',
            header: 'Flags',
            render: (v) => (
                <div className="flex gap-1 flex-wrap">
                    {v.is_vat_registered && (
                        <span className="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-medium text-blue-800">
                            VAT
                        </span>
                    )}
                    {v.is_government_supplier && (
                        <span className="rounded-full bg-purple-100 px-2 py-0.5 text-[10px] font-medium text-purple-800">
                            GOV
                        </span>
                    )}
                    {v.is_top_withholding_agent && (
                        <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-800">
                            TWA
                        </span>
                    )}
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            render: (v) =>
                v.is_active ? (
                    <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">
                        Active
                    </span>
                ) : (
                    <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                        Inactive
                    </span>
                ),
        },
    ];

    return (
        <div className="container py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">Vendors</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Supplier master. ATC and withholding rate defaulted to vendor bills and
                        Form 2307 issuance. TWA flag requires 1% higher withholding per RR 11-2018.
                    </p>
                </div>

                <div className="flex items-center gap-3 text-sm">
                    <input
                        type="search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search name / TIN / vendor #"
                        className="rounded-md border bg-background px-2 py-1"
                    />
                    <label className="flex items-center gap-1">
                        <input
                            type="checkbox"
                            checked={activeOnly}
                            onChange={(e) => setActiveOnly(e.target.checked)}
                        />
                        Active only
                    </label>
                    {canCreate && (
                        <a
                            href="#/procurement/vendors/new"
                            className="rounded-md bg-primary px-3 py-1.5 font-medium text-primary-foreground hover:opacity-90"
                        >
                            + New Vendor
                        </a>
                    )}
                </div>
            </header>

            <div className="mt-6">
                <DataTable
                    columns={columns}
                    rows={vendors}
                    rowKey={(v) => v.id}
                    isLoading={isLoading}
                    emptyState="No vendors match the current filters."
                />
            </div>
        </div>
    );
}
