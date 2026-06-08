import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';

import { formatPhp } from '@/shared/lib/money';

import {
    useGenerate1701,
    useGenerate1701Q,
    useGenerate1702Q,
    useGenerate1702RT,
    type BirForm,
} from '../api/bir-forms';

/**
 * ITR Wizard
 * ────────────────────────────────────────────────────────────────────────
 * Step 1: pick taxpayer type (individual / corporate) and period (Q1/Q2/Q3/Annual)
 * Step 2: select deduction regime (itemized / OSD / 8% flat for individuals)
 *         — disabled options reflect already-locked elections for the year
 * Step 3: enter credits (prior excess, quarterly payments, creditable WT)
 *         — autofilled from prior filings when annual is selected
 * Step 4: review computed lines, click "Generate" → submit to /tax-forms/…/generate
 * Step 5: success screen with PDF download link + "Mark as filed" CTA
 */

const wizardSchema = z.object({
    taxpayer_type: z.enum(['individual', 'corporate']),
    period: z.enum(['Q1', 'Q2', 'Q3', 'Annual']),
    year: z.number().int().min(2018).max(2099),
    use_osd: z.boolean().default(false),
    elect_flat_8pct: z.boolean().default(false),
    personal_exemption: z.string().default('0.00'),
    prior_excess_credits: z.string().default('0.00'),
    quarterly_payments: z.string().default('0.00'),
    creditable_wt: z.string().default('0.00'),
});

type WizardForm = z.infer<typeof wizardSchema>;

export function ItrWizardPage() {
    const [step, setStep] = useState(1);
    const [generatedForm, setGeneratedForm] = useState<BirForm | null>(null);

    const form = useForm<WizardForm>({
        resolver: zodResolver(wizardSchema),
        defaultValues: {
            taxpayer_type: 'individual',
            period: 'Q1',
            year: new Date().getFullYear(),
            use_osd: false,
            elect_flat_8pct: false,
            personal_exemption: '0.00',
            prior_excess_credits: '0.00',
            quarterly_payments: '0.00',
            creditable_wt: '0.00',
        },
    });

    const values = form.watch();

    const gen1701Q = useGenerate1701Q();
    const gen1702Q = useGenerate1702Q();
    const gen1701 = useGenerate1701();
    const gen1702RT = useGenerate1702RT();

    const submit = form.handleSubmit(async (data) => {
        const onOk = (f: BirForm) => {
            setGeneratedForm(f);
            setStep(5);
        };

        if (data.taxpayer_type === 'individual' && data.period !== 'Annual') {
            const q = Number(data.period.slice(1)) as 1 | 2 | 3;
            gen1701Q.mutate({ ...data, quarter: q }, { onSuccess: onOk });
        } else if (data.taxpayer_type === 'corporate' && data.period !== 'Annual') {
            const q = Number(data.period.slice(1)) as 1 | 2 | 3;
            gen1702Q.mutate({ ...data, quarter: q }, { onSuccess: onOk });
        } else if (data.taxpayer_type === 'individual') {
            gen1701.mutate(data, { onSuccess: onOk });
        } else {
            gen1702RT.mutate(data, { onSuccess: onOk });
        }
    });

    const isPending = gen1701Q.isPending || gen1702Q.isPending || gen1701.isPending || gen1702RT.isPending;
    const error =
        gen1701Q.error ?? gen1702Q.error ?? gen1701.error ?? gen1702RT.error;

    return (
        <div className="container max-w-3xl py-8">
            <header className="mb-6">
                <h1 className="text-2xl font-semibold">Income Tax Return Wizard</h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    Generate a 1701Q / 1702Q quarterly or 1701 / 1702-RT annual return.
                    Deduction-regime election is locked at the first filing for the year.
                </p>
            </header>

            <StepIndicator current={step} />

            <form onSubmit={submit} className="mt-6 space-y-6 rounded-lg border bg-card p-6">
                {step === 1 && (
                    <Step1
                        form={form}
                        onNext={() => setStep(2)}
                    />
                )}
                {step === 2 && (
                    <Step2
                        form={form}
                        onBack={() => setStep(1)}
                        onNext={() => setStep(3)}
                    />
                )}
                {step === 3 && (
                    <Step3
                        form={form}
                        onBack={() => setStep(2)}
                        onNext={() => setStep(4)}
                    />
                )}
                {step === 4 && (
                    <Step4Review
                        values={values}
                        onBack={() => setStep(3)}
                        isPending={isPending}
                        error={error}
                    />
                )}
                {step === 5 && generatedForm && (
                    <Step5Result form={generatedForm} onReset={() => { setStep(1); setGeneratedForm(null); }} />
                )}
            </form>
        </div>
    );
}

function StepIndicator({ current }: { current: number }) {
    const labels = ['Period', 'Regime', 'Credits', 'Review', 'Done'];
    return (
        <ol className="flex items-center gap-2 text-xs">
            {labels.map((label, i) => {
                const n = i + 1;
                const state =
                    n < current ? 'done' : n === current ? 'active' : 'todo';
                return (
                    <li key={label} className="flex items-center gap-2">
                        <span
                            className={`flex h-6 w-6 items-center justify-center rounded-full border ${
                                state === 'active'
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : state === 'done'
                                      ? 'border-emerald-500 bg-emerald-500 text-white'
                                      : 'border-muted-foreground/30 text-muted-foreground'
                            }`}
                        >
                            {n}
                        </span>
                        <span className={state === 'active' ? 'font-medium' : 'text-muted-foreground'}>
                            {label}
                        </span>
                        {n < labels.length && <span className="text-muted-foreground">→</span>}
                    </li>
                );
            })}
        </ol>
    );
}

type StepProps = { form: ReturnType<typeof useForm<WizardForm>>; onNext: () => void; onBack?: () => void };

function Step1({ form, onNext }: StepProps) {
    return (
        <>
            <div className="space-y-2">
                <label className="text-sm font-medium">Taxpayer type</label>
                <div className="flex gap-3">
                    {(['individual', 'corporate'] as const).map((t) => (
                        <label key={t} className="flex items-center gap-2 text-sm">
                            <input
                                type="radio"
                                value={t}
                                {...form.register('taxpayer_type')}
                            />
                            <span className="capitalize">{t}</span>
                        </label>
                    ))}
                </div>
            </div>

            <div className="space-y-2">
                <label className="text-sm font-medium">Period</label>
                <select
                    {...form.register('period')}
                    className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                >
                    <option value="Q1">Q1 (cumulative Jan–Mar)</option>
                    <option value="Q2">Q2 (cumulative Jan–Jun)</option>
                    <option value="Q3">Q3 (cumulative Jan–Sep)</option>
                    <option value="Annual">Annual (full year)</option>
                </select>
            </div>

            <div className="space-y-2">
                <label className="text-sm font-medium">Fiscal year</label>
                <input
                    type="number"
                    {...form.register('year', { valueAsNumber: true })}
                    className="w-32 rounded-md border bg-background px-3 py-2 text-sm"
                />
            </div>

            <div className="flex justify-end">
                <button
                    type="button"
                    onClick={onNext}
                    className="rounded-md bg-primary px-4 py-1.5 text-sm font-medium text-primary-foreground hover:opacity-90"
                >
                    Next
                </button>
            </div>
        </>
    );
}

function Step2({ form, onBack, onNext }: StepProps) {
    const taxpayer = form.watch('taxpayer_type');
    const useOsd = form.watch('use_osd');
    const elect8 = form.watch('elect_flat_8pct');

    return (
        <>
            <div>
                <h3 className="text-base font-medium">Deduction regime</h3>
                <p className="text-xs text-muted-foreground">
                    This is locked for the year once the first quarterly is filed.
                    To change after locking, use the OSD Elections page (BIR amendment required).
                </p>
            </div>

            <label className="flex items-start gap-3 rounded-md border p-3 text-sm">
                <input
                    type="checkbox"
                    {...form.register('use_osd')}
                    disabled={elect8}
                    className="mt-0.5"
                />
                <div>
                    <div className="font-medium">Elect OSD (40%)</div>
                    <div className="text-xs text-muted-foreground">
                        {taxpayer === 'individual'
                            ? '40% of gross sales, replacing itemized deductions (RR 2-2010).'
                            : '40% of gross income (sales − COGS), replacing itemized deductions (RR 2-2010).'}
                    </div>
                </div>
            </label>

            {taxpayer === 'individual' && (
                <label className="flex items-start gap-3 rounded-md border p-3 text-sm">
                    <input
                        type="checkbox"
                        {...form.register('elect_flat_8pct')}
                        disabled={useOsd}
                        className="mt-0.5"
                    />
                    <div>
                        <div className="font-medium">Elect 8% flat rate (TRAIN, RA 10963)</div>
                        <div className="text-xs text-muted-foreground">
                            8% of gross sales in lieu of graduated rates + percentage tax.
                            Available only when gross ≤ ₱3M VAT threshold. Cannot combine with OSD.
                        </div>
                    </div>
                </label>
            )}

            <div className="flex justify-between">
                <button type="button" onClick={onBack} className="rounded-md border px-3 py-1.5 text-sm">
                    Back
                </button>
                <button
                    type="button"
                    onClick={onNext}
                    className="rounded-md bg-primary px-4 py-1.5 text-sm font-medium text-primary-foreground"
                >
                    Next
                </button>
            </div>
        </>
    );
}

function Step3({ form, onBack, onNext }: StepProps) {
    const period = form.watch('period');
    const taxpayer = form.watch('taxpayer_type');

    return (
        <>
            <div>
                <h3 className="text-base font-medium">Credits and prior payments</h3>
                <p className="text-xs text-muted-foreground">
                    Leave at 0.00 to let the system auto-sum from prior Q-returns and
                    recorded 2307 certificates.
                </p>
            </div>

            <div className="grid grid-cols-2 gap-4">
                <Field
                    label="Creditable WT (2307 received)"
                    name="creditable_wt"
                    form={form}
                    hint="Auto-summed from tax.form_2307_received when 0."
                />
                {period === 'Annual' && (
                    <>
                        <Field
                            label="Quarterly payments YTD"
                            name="quarterly_payments"
                            form={form}
                            hint="Auto-summed from filed 1701Q/1702Q when 0."
                        />
                        <Field
                            label="Prior-year excess credits"
                            name="prior_excess_credits"
                            form={form}
                        />
                    </>
                )}
                {taxpayer === 'individual' && period === 'Annual' && (
                    <Field
                        label="Personal exemption (legacy)"
                        name="personal_exemption"
                        form={form}
                        hint="TRAIN removed personal exemptions; leave 0.00 for filings post-2018."
                    />
                )}
            </div>

            <div className="flex justify-between">
                <button type="button" onClick={onBack} className="rounded-md border px-3 py-1.5 text-sm">
                    Back
                </button>
                <button
                    type="button"
                    onClick={onNext}
                    className="rounded-md bg-primary px-4 py-1.5 text-sm font-medium text-primary-foreground"
                >
                    Review
                </button>
            </div>
        </>
    );
}

function Field({
    label,
    name,
    form,
    hint,
}: {
    label: string;
    name: keyof WizardForm;
    form: ReturnType<typeof useForm<WizardForm>>;
    hint?: string;
}) {
    return (
        <div className="space-y-1">
            <label className="text-sm font-medium">{label}</label>
            <input
                type="text"
                inputMode="decimal"
                {...form.register(name)}
                className="w-full rounded-md border bg-background px-3 py-2 font-mono text-sm tabular-nums"
            />
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
        </div>
    );
}

function Step4Review({
    values,
    onBack,
    isPending,
    error,
}: {
    values: WizardForm;
    onBack: () => void;
    isPending: boolean;
    error: unknown;
}) {
    const formType =
        values.taxpayer_type === 'individual'
            ? values.period === 'Annual' ? '1701' : '1701Q'
            : values.period === 'Annual' ? '1702-RT' : '1702Q';

    const regime = values.elect_flat_8pct
        ? '8% Flat'
        : values.use_osd
          ? 'OSD (40%)'
          : 'Itemized';

    return (
        <>
            <div>
                <h3 className="text-base font-medium">Review</h3>
                <p className="text-xs text-muted-foreground">
                    Generating <strong>{formType}</strong> for <strong>{values.year}</strong>
                    {values.period !== 'Annual' ? ` ${values.period}` : ''}.
                </p>
            </div>

            <dl className="grid grid-cols-2 gap-3 text-sm">
                <Cell label="Form" value={formType} />
                <Cell label="Period" value={`${values.year} ${values.period}`} />
                <Cell label="Taxpayer" value={values.taxpayer_type} />
                <Cell label="Regime" value={regime} />
                <Cell label="Creditable WT" value={formatPhp(values.creditable_wt)} />
                {values.period === 'Annual' && (
                    <>
                        <Cell label="Q payments YTD" value={formatPhp(values.quarterly_payments)} />
                        <Cell label="Prior excess credits" value={formatPhp(values.prior_excess_credits)} />
                    </>
                )}
            </dl>

            {error !== null && error !== undefined && (
                <div className="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                    {(error as { response?: { data?: { message?: string } } })?.response?.data
                        ?.message ?? 'Generation failed.'}
                </div>
            )}

            <div className="flex justify-between">
                <button
                    type="button"
                    onClick={onBack}
                    disabled={isPending}
                    className="rounded-md border px-3 py-1.5 text-sm disabled:opacity-50"
                >
                    Back
                </button>
                <button
                    type="submit"
                    disabled={isPending}
                    className="rounded-md bg-primary px-4 py-1.5 text-sm font-medium text-primary-foreground disabled:opacity-50"
                >
                    {isPending ? 'Generating…' : `Generate ${formType}`}
                </button>
            </div>
        </>
    );
}

function Cell({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs uppercase tracking-wide text-muted-foreground">{label}</dt>
            <dd className="font-medium tabular-nums">{value}</dd>
        </div>
    );
}

function Step5Result({ form, onReset }: { form: BirForm; onReset: () => void }) {
    return (
        <>
            <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                <h3 className="text-base font-medium text-emerald-900">
                    Form generated successfully
                </h3>
                <p className="mt-1 text-sm text-emerald-800">
                    {form.form_type} {form.period.year}
                    {form.period.quarter ? ` Q${form.period.quarter}` : ''} —
                    Tax due: <strong>{formatPhp(form.tax_due)}</strong>
                </p>
            </div>

            <div className="space-y-3 text-sm">
                <h4 className="font-medium">Line breakdown</h4>
                <table className="w-full">
                    <tbody>
                        {form.lines.slice(0, 12).map((l) => (
                            <tr key={l.line_code} className="border-t">
                                <td className="px-2 py-1 font-mono text-xs text-muted-foreground">
                                    {l.line_code}
                                </td>
                                <td className="px-2 py-1">{l.description}</td>
                                <td className="px-2 py-1 text-right tabular-nums">
                                    {formatPhp(l.amount)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="flex justify-between">
                <button type="button" onClick={onReset} className="rounded-md border px-3 py-1.5 text-sm">
                    Generate another
                </button>
                {form.pdf_path && (
                    <a
                        href={`/api/v1/tax-forms/${form.id}/pdf`}
                        target="_blank"
                        rel="noreferrer"
                        className="rounded-md bg-primary px-4 py-1.5 text-sm font-medium text-primary-foreground"
                    >
                        Download PDF
                    </a>
                )}
            </div>
        </>
    );
}
