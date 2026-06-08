import { useState } from 'react';

import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';
import { formatPhp } from '@/shared/lib/money';

import {
    useGenerate13thMonthRun,
    useGenerateForm1601C,
    useGenerateForm2316Batch,
    useGeneratePagIbig,
    useGeneratePhilHealth,
    useGenerateSssR3,
    type StatutoryRemittanceResult,
    type ThirteenthMonthRun,
} from '../api/payroll-forms';

/**
 * Payroll-driven BIR forms + statutory remittance launcher.
 *
 * Surfaces:
 *   • 13th month pay run  (PD 851 — Generate13thMonthRun action)
 *   • 1601-C (monthly compensation WHT)
 *   • 2316   (annual certificate per employee)
 *
 * SSS R-3 / PhilHealth RF-1 / Pag-IBIG MCRF remittance files are wired.
 * 13th month pay run (PD 851) is available via the Generate13thMonthRun action.
 * 1604-CF (annual alphalist) is routed through the Tax module — link provided below.
 */
export function PayrollFormsPage() {
    const user = useAuthStore((s) => s.user);

    const today = new Date();
    const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().slice(0, 10);
    const endOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().slice(0, 10);

    const [periodFrom, setPeriodFrom] = useState(startOfMonth);
    const [periodTo, setPeriodTo] = useState(endOfMonth);
    const [year, setYear] = useState(today.getFullYear());

    const gen1601C  = useGenerateForm1601C();
    const gen2316   = useGenerateForm2316Batch();
    const gen13th   = useGenerate13thMonthRun();

    const canFile    = hasPermission(user, 'payroll.statutory.file');
    const canCompute = hasPermission(user, 'payroll.runs.compute');

    return (
        <div className="container py-8">
            <header>
                <h1 className="text-2xl font-semibold">Payroll BIR Forms</h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    Monthly + annual BIR forms and statutory remittance files driven by approved
                    payroll runs. 1604-CF annual alphalist is accessible from the ITR Wizard.
                </p>
            </header>

            {/* ── 13th month pay (PD 851) ─────────────────────────────── */}
            <section className="mt-6 rounded-lg border bg-card p-5">
                <div className="flex items-baseline justify-between">
                    <div>
                        <h2 className="text-base font-medium">13th Month Pay Run</h2>
                        <p className="text-xs text-muted-foreground">
                            PD 851 — mandatory annual benefit. Sums each employee's BASIC salary from
                            approved payroll runs for the year and divides by 12. Non-taxable up to
                            ₱90,000 (RA 10963 / TRAIN Law). Due by December 24.
                        </p>
                    </div>
                    <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-800">
                        PD 851
                    </span>
                </div>

                <div className="mt-4 flex items-end gap-3">
                    <div>
                        <FieldLabel>Year</FieldLabel>
                        <input
                            type="number"
                            value={year}
                            onChange={(e) => setYear(Number(e.target.value))}
                            className="mt-1 w-24 rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </div>
                    <button
                        type="button"
                        onClick={() => gen13th.mutate({ year })}
                        disabled={gen13th.isPending || !canCompute}
                        className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                    >
                        {gen13th.isPending ? 'Generating…' : 'Generate 13th Month Run'}
                    </button>
                </div>

                {gen13th.data && <ThirteenthMonthBanner run={gen13th.data} />}
                {gen13th.isError && <ErrorBanner error={gen13th.error} />}
            </section>

            {/* ── 1604-CF cross-link ──────────────────────────────────── */}
            <section className="mt-4 flex items-center gap-3 rounded-lg border border-dashed bg-muted/30 px-5 py-3">
                <div className="flex-1">
                    <span className="text-sm font-medium">BIR Form 1604-CF</span>
                    <span className="ml-2 text-xs text-muted-foreground">
                        Annual Information Return of Income Taxes Withheld on Compensation.
                        Includes Schedule 7.1 (MWE) and 7.2 (non-MWE) alphalist.
                        Due January 31.
                    </span>
                </div>
                <a
                    href="#/tax/itr-wizard"
                    className="shrink-0 rounded-md border px-3 py-1.5 text-xs hover:bg-accent"
                >
                    Go to ITR Wizard →
                </a>
            </section>

            {/* ── 1601-C ──────────────────────────────────────────────── */}
            <section className="mt-6 rounded-lg border bg-card p-5">
                <div className="flex items-baseline justify-between">
                    <div>
                        <h2 className="text-base font-medium">BIR Form 1601-C</h2>
                        <p className="text-xs text-muted-foreground">
                            Monthly Remittance Return of Income Taxes Withheld on Compensation.
                            Sums WHT booked across the period's approved payroll runs.
                            Due 10th of the following month.
                        </p>
                    </div>
                </div>

                <div className="mt-4 flex items-end gap-3">
                    <div>
                        <FieldLabel>Period from</FieldLabel>
                        <input
                            type="date"
                            value={periodFrom}
                            onChange={(e) => setPeriodFrom(e.target.value)}
                            className="mt-1 rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </div>
                    <div>
                        <FieldLabel>Period to</FieldLabel>
                        <input
                            type="date"
                            value={periodTo}
                            onChange={(e) => setPeriodTo(e.target.value)}
                            className="mt-1 rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </div>
                    <button
                        type="button"
                        onClick={() => gen1601C.mutate({ period_from: periodFrom, period_to: periodTo })}
                        disabled={gen1601C.isPending || !canFile}
                        className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                    >
                        {gen1601C.isPending ? 'Generating…' : 'Generate 1601-C'}
                    </button>
                </div>

                {gen1601C.data && (
                    <ResultBanner
                        title="1601-C generated"
                        details={[
                            `Total compensation: ${formatPhp(gen1601C.data.total_compensation)}`,
                            `Total withholding: ${formatPhp(gen1601C.data.total_withholding)}`,
                        ]}
                        pdfPath={gen1601C.data.pdf_path}
                    />
                )}
                {gen1601C.isError && <ErrorBanner error={gen1601C.error} />}
            </section>

            {/* ── 2316 ────────────────────────────────────────────────── */}
            <section className="mt-6 rounded-lg border bg-card p-5">
                <div className="flex items-baseline justify-between">
                    <div>
                        <h2 className="text-base font-medium">BIR Form 2316 (annual)</h2>
                        <p className="text-xs text-muted-foreground">
                            Certificate of Compensation Payment / Tax Withheld. One PDF per
                            employee, bundled into a ZIP. Distributed to employees by Jan 31.
                        </p>
                    </div>
                </div>

                <div className="mt-4 flex items-end gap-3">
                    <div>
                        <FieldLabel>Year</FieldLabel>
                        <input
                            type="number"
                            value={year}
                            onChange={(e) => setYear(Number(e.target.value))}
                            className="mt-1 w-24 rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </div>
                    <button
                        type="button"
                        onClick={() => gen2316.mutate({ year })}
                        disabled={gen2316.isPending || !canFile}
                        className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                    >
                        {gen2316.isPending ? 'Generating batch…' : 'Generate 2316 batch'}
                    </button>
                </div>

                {gen2316.data && (
                    <ResultBanner
                        title="2316 batch generated"
                        details={[
                            `Employees: ${gen2316.data.employee_count}`,
                            `Generated at: ${gen2316.data.generated_at}`,
                        ]}
                        pdfPath={gen2316.data.pdf_zip_path}
                        downloadLabel="Download ZIP"
                    />
                )}
                {gen2316.isError && <ErrorBanner error={gen2316.error} />}
            </section>

            {/* ── Statutory remittance files (live) ───────────────────── */}
            <section className="mt-6 rounded-lg border bg-card p-5">
                <h2 className="text-base font-medium">Statutory remittance files</h2>
                <p className="mt-1 text-xs text-muted-foreground">
                    Aggregates per-employee SSS / PhilHealth / Pag-IBIG contributions from
                    approved payroll runs in the period. Each agency's portal accepts the
                    generated CSV directly.
                </p>

                <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                    <RemittanceTile
                        title="SSS R-3"
                        description="Monthly employer remittance schedule of SS contributions."
                        rrCitation="SSS Circular 2020-033"
                        hook={useGenerateSssR3()}
                        canFile={canFile}
                        periodFrom={periodFrom}
                        periodTo={periodTo}
                    />
                    <RemittanceTile
                        title="PhilHealth RF-1"
                        description="Monthly Employer's Remittance Report."
                        rrCitation="PhilHealth Circular 2020-005"
                        hook={useGeneratePhilHealth()}
                        canFile={canFile}
                        periodFrom={periodFrom}
                        periodTo={periodTo}
                    />
                    <RemittanceTile
                        title="Pag-IBIG MCRF"
                        description="Member's Contribution Remittance Form (monthly)."
                        rrCitation="HDMF Circular 460"
                        hook={useGeneratePagIbig()}
                        canFile={canFile}
                        periodFrom={periodFrom}
                        periodTo={periodTo}
                    />
                </div>
            </section>
        </div>
    );
}

function ThirteenthMonthBanner({ run }: { run: ThirteenthMonthRun }) {
    return (
        <div className="mt-4 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">
            <div className="font-medium">✓ 13th month run {run.run_no} — {run.status}</div>
            <ul className="mt-1 list-disc pl-4 text-xs">
                <li>Period: {run.period_start} to {run.period_end}</li>
                <li>Employees: {run.payslip_count}</li>
                <li>Total gross: {formatPhp(run.total_gross)}</li>
                <li>Total net pay: {formatPhp(run.total_net)}</li>
            </ul>
            <a
                href="#/payroll/runs"
                className="mt-2 inline-block rounded-md border border-emerald-300 bg-white px-3 py-1 text-xs font-medium text-emerald-800 hover:bg-emerald-100"
            >
                View in Payroll Runs →
            </a>
        </div>
    );
}

interface RemittanceHook {
    mutate: (input: { period_from: string; period_to: string }) => void;
    data?: StatutoryRemittanceResult | undefined;
    isPending: boolean;
    error: unknown;
}

function RemittanceTile({
    title,
    description,
    rrCitation,
    hook,
    canFile,
    periodFrom,
    periodTo,
}: {
    title: string;
    description: string;
    rrCitation: string;
    hook: RemittanceHook;
    canFile: boolean;
    periodFrom: string;
    periodTo: string;
}) {
    return (
        <div className="rounded-md border bg-card p-3">
            <h3 className="text-sm font-medium">{title}</h3>
            <p className="mt-1 text-xs text-muted-foreground">{description}</p>
            <p className="mt-1 text-[10px] font-mono text-muted-foreground">{rrCitation}</p>

            {hook.data && (
                <div className="mt-2 rounded-md border border-emerald-200 bg-emerald-50 p-2 text-xs text-emerald-900">
                    <div className="font-medium">✓ {hook.data.line_count} employees</div>
                    <div>Total: ₱{hook.data.total_remittance}</div>
                    <a
                        href={`/api/v1/storage/${hook.data.storage_path}`}
                        target="_blank"
                        rel="noreferrer"
                        className="mt-1 inline-block text-emerald-800 underline"
                    >
                        Download CSV
                    </a>
                </div>
            )}

            {hook.error !== null && hook.error !== undefined && (
                <div className="mt-2 rounded-md border border-destructive/30 bg-destructive/5 p-2 text-[10px] text-destructive">
                    {(hook.error as { response?: { data?: { message?: string } } })?.response?.data
                        ?.message ?? 'Generation failed.'}
                </div>
            )}

            <button
                type="button"
                onClick={() => hook.mutate({ period_from: periodFrom, period_to: periodTo })}
                disabled={hook.isPending || !canFile}
                className="mt-3 w-full rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
            >
                {hook.isPending ? 'Generating…' : `Generate ${title}`}
            </button>
        </div>
    );
}

function FieldLabel({ children }: { children: React.ReactNode }) {
    return (
        <label className="block text-xs font-medium uppercase tracking-wide text-muted-foreground">
            {children}
        </label>
    );
}

function ResultBanner({
    title,
    details,
    pdfPath,
    downloadLabel = 'Download PDF',
}: {
    title: string;
    details: string[];
    pdfPath: string | null;
    downloadLabel?: string;
}) {
    return (
        <div className="mt-4 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">
            <div className="font-medium">✓ {title}</div>
            <ul className="mt-1 list-disc pl-4 text-xs">
                {details.map((d, i) => (
                    <li key={i}>{d}</li>
                ))}
            </ul>
            {pdfPath && (
                <a
                    href={`/api/v1/storage/${pdfPath}`}
                    target="_blank"
                    rel="noreferrer"
                    className="mt-2 inline-block rounded-md border border-emerald-300 bg-white px-3 py-1 text-xs font-medium text-emerald-800 hover:bg-emerald-100"
                >
                    {downloadLabel}
                </a>
            )}
        </div>
    );
}

function ErrorBanner({ error }: { error: unknown }) {
    return (
        <div className="mt-4 rounded-md border border-destructive/30 bg-destructive/5 p-3 text-xs text-destructive">
            {(error as { response?: { data?: { message?: string } } })?.response?.data?.message ??
                'Generation failed.'}
        </div>
    );
}

