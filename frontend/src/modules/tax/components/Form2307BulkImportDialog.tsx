import { useState } from 'react';

import { useBulkImport2307 } from '../api/form-2307-received';

const TEMPLATE_HEADER =
    'payor_tin,payor_registered_name,payor_branch_code,payor_address,certificate_no,atc_code,period_from,period_to,income_payment,tax_withheld';

const TEMPLATE_EXAMPLE =
    '999-888-777-000,ACME CORP,000,Quezon City,C-2026-Q1-0001,WI010,2026-01-01,2026-03-31,100000.00,5000.00';

export function Form2307BulkImportDialog({ onClose }: { onClose: () => void }) {
    const [csv, setCsv] = useState('');
    const { mutate, data: report, isPending, reset } = useBulkImport2307();

    const handleFileUpload = async (file: File) => {
        const text = await file.text();
        setCsv(text);
    };

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            onClick={onClose}
        >
            <div
                onClick={(e) => e.stopPropagation()}
                className="max-h-[90vh] w-full max-w-3xl space-y-4 overflow-y-auto rounded-lg bg-card p-6 shadow-lg"
            >
                <header>
                    <h2 className="text-lg font-semibold">Bulk import 2307 certificates</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Paste a CSV or upload a file. Headers must match the BIR-aligned format below.
                        Duplicates are skipped (not failed) — safe to re-upload after fixing errors.
                    </p>
                </header>

                <pre className="overflow-x-auto rounded-md border bg-muted/40 p-2 font-mono text-xs">
                    {TEMPLATE_HEADER}
                    {'\n'}
                    {TEMPLATE_EXAMPLE}
                </pre>

                {!report && (
                    <>
                        <div className="flex items-center gap-3">
                            <label className="cursor-pointer rounded-md border px-3 py-1.5 text-sm hover:bg-accent">
                                Upload CSV file
                                <input
                                    type="file"
                                    accept=".csv,text/csv"
                                    onChange={(e) => {
                                        const f = e.target.files?.[0];
                                        if (f) handleFileUpload(f);
                                    }}
                                    className="hidden"
                                />
                            </label>
                            <span className="text-xs text-muted-foreground">
                                or paste the CSV body below
                            </span>
                        </div>

                        <textarea
                            rows={12}
                            value={csv}
                            onChange={(e) => setCsv(e.target.value)}
                            placeholder={`${TEMPLATE_HEADER}\n${TEMPLATE_EXAMPLE}`}
                            className="w-full rounded-md border bg-background px-3 py-2 font-mono text-xs"
                        />

                        <div className="flex items-center justify-end gap-2">
                            <button
                                type="button"
                                onClick={onClose}
                                className="rounded-md border px-3 py-1.5 text-sm hover:bg-accent"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                onClick={() => mutate(csv)}
                                disabled={!csv.trim() || isPending}
                                className="rounded-md bg-primary px-4 py-1.5 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                            >
                                {isPending ? 'Importing…' : 'Import'}
                            </button>
                        </div>
                    </>
                )}

                {report && (
                    <div className="space-y-3">
                        <div className="grid grid-cols-4 gap-2 text-center">
                            <Stat label="Total" value={report.summary.total} />
                            <Stat label="Successful" value={report.summary.successful} accent="emerald" />
                            <Stat label="Duplicates" value={report.summary.duplicates} accent="amber" />
                            <Stat label="Failed" value={report.summary.failed} accent="destructive" />
                        </div>

                        {report.failed.length > 0 && (
                            <ErrorBlock title="Failed rows" rows={report.failed} variant="destructive" />
                        )}
                        {report.duplicates.length > 0 && (
                            <ErrorBlock
                                title="Duplicate rows (already on file)"
                                rows={report.duplicates}
                                variant="muted"
                            />
                        )}

                        <div className="flex items-center justify-end gap-2">
                            <button
                                type="button"
                                onClick={() => { reset(); setCsv(''); }}
                                className="rounded-md border px-3 py-1.5 text-sm hover:bg-accent"
                            >
                                Import another
                            </button>
                            <button
                                type="button"
                                onClick={onClose}
                                className="rounded-md bg-primary px-3 py-1.5 text-sm text-primary-foreground"
                            >
                                Done
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

function Stat({ label, value, accent }: { label: string; value: number; accent?: 'emerald' | 'amber' | 'destructive' }) {
    const colour =
        accent === 'emerald'
            ? 'text-emerald-700'
            : accent === 'amber'
              ? 'text-amber-700'
              : accent === 'destructive'
                ? 'text-destructive'
                : '';
    return (
        <div className="rounded-md border p-2">
            <div className="text-xs uppercase tracking-wide text-muted-foreground">{label}</div>
            <div className={`mt-1 text-xl font-semibold tabular-nums ${colour}`}>{value}</div>
        </div>
    );
}

function ErrorBlock({
    title,
    rows,
    variant,
}: {
    title: string;
    rows: Array<{ row_no: number; error_type: string; message: string }>;
    variant: 'destructive' | 'muted';
}) {
    const border =
        variant === 'destructive' ? 'border-destructive/30 bg-destructive/5' : 'border-muted bg-muted/30';

    return (
        <div className={`rounded-md border p-3 ${border}`}>
            <h3 className="text-sm font-medium">{title}</h3>
            <ul className="mt-2 space-y-1 text-xs">
                {rows.map((r, i) => (
                    <li key={i}>
                        <span className="font-mono text-muted-foreground">Row {r.row_no}</span>{' '}
                        <span className="text-muted-foreground">[{r.error_type}]</span> · {r.message}
                    </li>
                ))}
            </ul>
        </div>
    );
}
