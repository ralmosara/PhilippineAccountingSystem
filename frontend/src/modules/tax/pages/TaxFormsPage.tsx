import { useState } from 'react';

import { DataTable, type Column } from '@/shared/components/DataTable';
import { formatPhp } from '@/shared/lib/money';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

import { useBirForms, type BirForm } from '../api/bir-forms';

const FORM_LABELS: Record<string, string> = {
    '1601-C':  'Monthly Compensation WHT',
    '1601-E':  'Monthly EWT (expanded)',
    '1604-CF': 'Annual Info Return (compensation)',
    '1604-E':  'Annual Info Return (expanded)',
    '1701Q':   'Quarterly ITR (individual)',
    '1701':    'Annual ITR (individual)',
    '1702Q':   'Quarterly ITR (corporate)',
    '1702-RT': 'Annual ITR (corporate)',
    '2316':    'Certificate of Compensation (annual)',
    '2550M':   'Monthly VAT Return',
    '2550Q':   'Quarterly VAT Return',
};

const STATUS_STYLES: Record<BirForm['status'], string> = {
    draft:     'bg-slate-100 text-slate-700',
    generated: 'bg-blue-100 text-blue-800',
    filed:     'bg-emerald-100 text-emerald-800',
};

const CURRENT_YEAR = new Date().getFullYear();

export function TaxFormsPage() {
    const user = useAuthStore((s) => s.user);
    const canFile = hasPermission(user, 'tax.forms.file');

    const [formType, setFormType] = useState('');
    const [year, setYear] = useState<number | ''>(CURRENT_YEAR);
    const [status, setStatus] = useState('');

    const { data: forms, isLoading } = useBirForms({
        form_type: formType || undefined,
        year:      year !== '' ? year : undefined,
        status:    status || undefined,
    });

    const columns: Column<BirForm>[] = [
        {
            key: 'form_type',
            header: 'Form',
            render: (f) => (
                <div>
                    <span className="font-mono font-medium">{f.form_type}</span>
                    {FORM_LABELS[f.form_type] && (
                        <div className="text-xs text-muted-foreground">{FORM_LABELS[f.form_type]}</div>
                    )}
                </div>
            ),
        },
        {
            key: 'period',
            header: 'Period',
            render: (f) => {
                const p = f.period;
                if (p.quarter) return `${p.year} Q${p.quarter}`;
                if (p.from && p.to) return `${p.from} – ${p.to}`;
                return String(p.year);
            },
        },
        {
            key: 'tax_due',
            header: 'Tax Due',
            align: 'right',
            numeric: true,
            render: (f) => formatPhp(f.tax_due),
        },
        {
            key: 'tax_paid',
            header: 'Tax Paid',
            align: 'right',
            numeric: true,
            render: (f) => formatPhp(f.tax_paid),
        },
        {
            key: 'status',
            header: 'Status',
            render: (f) => (
                <span className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${STATUS_STYLES[f.status]}`}>
                    {f.status}
                </span>
            ),
        },
        {
            key: 'generated_at',
            header: 'Generated',
            render: (f) => f.generated_at ? f.generated_at.slice(0, 10) : '—',
        },
        {
            key: 'filed_at',
            header: 'Filed',
            render: (f) => f.filed_at ? f.filed_at.slice(0, 10) : '—',
        },
        {
            key: 'actions',
            header: '',
            render: (f) => (
                <div className="flex items-center gap-2">
                    {f.pdf_path && (
                        <a
                            href={`/api/v1/storage/${f.pdf_path}`}
                            target="_blank"
                            rel="noreferrer"
                            className="text-xs text-primary underline"
                        >
                            PDF
                        </a>
                    )}
                    {f.dat_path && (
                        <a
                            href={`/api/v1/storage/${f.dat_path}`}
                            target="_blank"
                            rel="noreferrer"
                            className="text-xs text-primary underline"
                        >
                            DAT
                        </a>
                    )}
                    {f.bir_filing_ref && (
                        <span className="font-mono text-[10px] text-muted-foreground">
                            Ref: {f.bir_filing_ref}
                        </span>
                    )}
                </div>
            ),
        },
    ];

    return (
        <div className="container py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">BIR Forms</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        All generated BIR returns across the company. Click PDF / DAT to download.
                        File status is updated after eBIRForms / EFPS acknowledgement.
                    </p>
                </div>

                <div className="flex items-center gap-2 text-sm flex-wrap justify-end">
                    <select
                        value={formType}
                        onChange={(e) => setFormType(e.target.value)}
                        className="rounded-md border bg-background px-2 py-1"
                    >
                        <option value="">All forms</option>
                        {Object.keys(FORM_LABELS).map((k) => (
                            <option key={k} value={k}>{k}</option>
                        ))}
                    </select>
                    <input
                        type="number"
                        value={year}
                        onChange={(e) => setYear(e.target.value ? Number(e.target.value) : '')}
                        placeholder="Year"
                        className="w-24 rounded-md border bg-background px-2 py-1"
                    />
                    <select
                        value={status}
                        onChange={(e) => setStatus(e.target.value)}
                        className="rounded-md border bg-background px-2 py-1"
                    >
                        <option value="">All statuses</option>
                        <option value="draft">Draft</option>
                        <option value="generated">Generated</option>
                        <option value="filed">Filed</option>
                    </select>
                </div>
            </header>

            {/* Quick-links to generators */}
            <div className="mt-4 flex flex-wrap gap-2 text-xs">
                <span className="text-muted-foreground self-center">Generate:</span>
                <a href="#/tax/itr-wizard"
                   className="rounded-md border px-2 py-1 hover:bg-accent">
                    ITR Wizard (1701 / 1702)
                </a>
                <a href="#/payroll/forms"
                   className="rounded-md border px-2 py-1 hover:bg-accent">
                    Payroll Forms (1601-C / 2316)
                </a>
                {canFile && (
                    <span className="ml-2 self-center text-muted-foreground">
                        · Filing (Mark as Filed) via POST /tax-forms/&#123;id&#125;/file — MFA required.
                    </span>
                )}
            </div>

            <div className="mt-6">
                <DataTable
                    columns={columns}
                    rows={forms}
                    rowKey={(f) => f.id}
                    isLoading={isLoading}
                    emptyState="No BIR forms generated yet. Use ITR Wizard or Payroll Forms to generate."
                />
            </div>
        </div>
    );
}
