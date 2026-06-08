import { useState } from 'react';

import { formatPhp } from '@/shared/lib/money';

import {
    useForm2307Received,
    type Form2307Received,
} from '../api/form-2307-received';
import { Form2307BulkImportDialog } from '../components/Form2307BulkImportDialog';

export function Form2307ReceivedPage() {
    const [year, setYear] = useState<number>(new Date().getFullYear());
    const [quarter, setQuarter] = useState<number | ''>('');
    const [statusFilter, setStatusFilter] = useState<string>('');
    const [importOpen, setImportOpen] = useState(false);

    const { data: certs, isLoading } = useForm2307Received({
        year,
        quarter: quarter || undefined,
        status: statusFilter || undefined,
    });

    return (
        <div className="container py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">2307 Certificates Received</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Income tax remitted by our customers/clients on our behalf. Sum of recorded
                        certificates becomes a tax credit on the matching 1701 / 1702-RT.
                    </p>
                </div>

                <div className="flex items-center gap-2 text-sm">
                    <input
                        type="number"
                        value={year}
                        onChange={(e) => setYear(Number(e.target.value))}
                        className="w-24 rounded-md border bg-background px-2 py-1"
                        title="Filter by tax year (period_to)"
                    />
                    <select
                        value={quarter}
                        onChange={(e) => setQuarter(e.target.value === '' ? '' : Number(e.target.value))}
                        className="rounded-md border bg-background px-2 py-1"
                    >
                        <option value="">All quarters</option>
                        <option value="1">Q1</option>
                        <option value="2">Q2</option>
                        <option value="3">Q3</option>
                        <option value="4">Q4</option>
                    </select>
                    <select
                        value={statusFilter}
                        onChange={(e) => setStatusFilter(e.target.value)}
                        className="rounded-md border bg-background px-2 py-1"
                    >
                        <option value="">All statuses</option>
                        <option value="recorded">Recorded</option>
                        <option value="claimed">Claimed</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <button
                        type="button"
                        onClick={() => setImportOpen(true)}
                        className="rounded-md border px-3 py-1.5 hover:bg-accent"
                    >
                        Import CSV
                    </button>
                </div>
            </header>

            <SummaryStrip certs={certs ?? []} />

            <div className="mt-6 overflow-hidden rounded-lg border bg-card">
                <table className="w-full text-sm">
                    <thead className="bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th className="px-4 py-2">Payor</th>
                            <th className="px-4 py-2">TIN</th>
                            <th className="px-4 py-2">Cert No</th>
                            <th className="px-4 py-2">ATC</th>
                            <th className="px-4 py-2">Period</th>
                            <th className="px-4 py-2 text-right">Income Payment</th>
                            <th className="px-4 py-2 text-right">Tax Withheld</th>
                            <th className="px-4 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {isLoading && (
                            <tr>
                                <td colSpan={8} className="px-4 py-6 text-center text-muted-foreground">
                                    Loading…
                                </td>
                            </tr>
                        )}
                        {!isLoading && certs?.length === 0 && (
                            <tr>
                                <td colSpan={8} className="px-4 py-6 text-center text-muted-foreground">
                                    No 2307 certificates on file for the selected filters. Import CSV
                                    or record manually.
                                </td>
                            </tr>
                        )}
                        {certs?.map((c) => (
                            <tr key={c.id} className="border-t hover:bg-muted/30">
                                <td className="px-4 py-2">{c.payor.registered_name}</td>
                                <td className="px-4 py-2 font-mono text-xs">{c.payor.tin}</td>
                                <td className="px-4 py-2 text-xs">{c.certificate_no ?? '—'}</td>
                                <td className="px-4 py-2 text-xs">{c.atc_code}</td>
                                <td className="px-4 py-2 text-xs text-muted-foreground">
                                    {c.period_from} – {c.period_to}
                                </td>
                                <td className="px-4 py-2 text-right tabular-nums">
                                    {formatPhp(c.income_payment)}
                                </td>
                                <td className="px-4 py-2 text-right tabular-nums font-medium">
                                    {formatPhp(c.tax_withheld)}
                                </td>
                                <td className="px-4 py-2">
                                    <CertStatusBadge status={c.status} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {importOpen && <Form2307BulkImportDialog onClose={() => setImportOpen(false)} />}
        </div>
    );
}

function SummaryStrip({ certs }: { certs: Form2307Received[] }) {
    const totals = certs.reduce(
        (acc, c) => {
            const tw = Number(c.tax_withheld) || 0;
            if (c.status === 'recorded') acc.recorded += tw;
            if (c.status === 'claimed') acc.claimed += tw;
            return acc;
        },
        { recorded: 0, claimed: 0 },
    );

    return (
        <div className="mt-4 grid grid-cols-3 gap-3">
            <Tile
                label="Recorded (claimable)"
                value={formatPhp(totals.recorded)}
                hint="Available as credit on the next ITR generated."
                accent="emerald"
            />
            <Tile
                label="Claimed"
                value={formatPhp(totals.claimed)}
                hint="Already applied to a filed 1701 / 1702-RT / 1701Q / 1702Q."
                accent="slate"
            />
            <Tile
                label="Total"
                value={formatPhp(totals.recorded + totals.claimed)}
                hint="Sum across all certificates for the selected filters."
                accent="primary"
            />
        </div>
    );
}

function Tile({
    label,
    value,
    hint,
    accent,
}: {
    label: string;
    value: string;
    hint: string;
    accent: 'emerald' | 'slate' | 'primary';
}) {
    const colour =
        accent === 'emerald'
            ? 'border-emerald-200 bg-emerald-50'
            : accent === 'slate'
              ? 'border-slate-200 bg-slate-50'
              : 'border-primary/30 bg-primary/5';
    return (
        <div className={`rounded-lg border p-4 ${colour}`}>
            <div className="text-xs uppercase tracking-wide text-muted-foreground">{label}</div>
            <div className="mt-1 text-xl font-semibold tabular-nums">{value}</div>
            <div className="mt-1 text-xs text-muted-foreground">{hint}</div>
        </div>
    );
}

function CertStatusBadge({ status }: { status: Form2307Received['status'] }) {
    const styles = {
        draft:    'bg-slate-100 text-slate-700',
        recorded: 'bg-blue-100 text-blue-800',
        claimed:  'bg-emerald-100 text-emerald-800',
        rejected: 'bg-amber-100 text-amber-800',
    } as const;
    return (
        <span className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${styles[status]}`}>
            {status}
        </span>
    );
}
