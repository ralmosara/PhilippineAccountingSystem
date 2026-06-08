import { useState } from 'react';

import { formatPhp } from '@/shared/lib/money';

import {
    useGenerateBalanceSheet,
    useGenerateCashDisbursementsBook,
    useGenerateCashFlow,
    useGenerateCashReceiptsBook,
    useGenerateEquityStatement,
    useGenerateGeneralJournal,
    useGenerateGeneralLedger,
    useGenerateIncomeStatement,
    useGeneratePurchasesBook,
    useGenerateSalesBook,
    useGenerateTrialBalance,
    type BalanceSheet,
    type CashFlowStatement,
    type EquityStatement,
    type IncomeStatement,
    type JournalBookResult,
    type SubsidiaryBookResult,
    type TrialBalance,
} from '../api/reports';

type Tab = 'trial_balance' | 'balance_sheet' | 'income_statement' | 'cash_flow' | 'equity';

const yearStart = () => new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10);
const today = () => new Date().toISOString().slice(0, 10);

export function ReportingDashboardPage() {
    const [tab, setTab] = useState<Tab>('trial_balance');
    const [from, setFrom] = useState(yearStart());
    const [to, setTo] = useState(today());

    const tb  = useGenerateTrialBalance();
    const bs  = useGenerateBalanceSheet();
    const is_ = useGenerateIncomeStatement();
    const cf  = useGenerateCashFlow();
    const es  = useGenerateEquityStatement();

    const gj   = useGenerateGeneralJournal();
    const gl   = useGenerateGeneralLedger();
    const sb   = useGenerateSalesBook();
    const pb   = useGeneratePurchasesBook();
    const crb  = useGenerateCashReceiptsBook();
    const cdb  = useGenerateCashDisbursementsBook();

    const handleGenerate = () => {
        if (tab === 'trial_balance')    tb.mutate({ as_of: to });
        if (tab === 'balance_sheet')    bs.mutate({ as_of: to });
        if (tab === 'income_statement') is_.mutate({ from, to });
        if (tab === 'cash_flow')        cf.mutate({ from, to });
        if (tab === 'equity')           es.mutate({ from, to });
    };

    return (
        <div className="container py-8">
            <header>
                <h1 className="text-2xl font-semibold">Reports</h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    PFRS-aligned financial statements. Trial Balance + Balance Sheet are "as-of"; Income
                    Statement + Cash Flow are "for the period".
                </p>
            </header>

            <Tabs current={tab} onChange={setTab} />

            <div className="mt-4 flex items-end gap-3">
                {(tab === 'income_statement' || tab === 'cash_flow' || tab === 'equity') && (
                    <div>
                        <label className="block text-xs uppercase tracking-wide text-muted-foreground">From</label>
                        <input
                            type="date"
                            value={from}
                            onChange={(e) => setFrom(e.target.value)}
                            className="mt-1 rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </div>
                )}
                <div>
                    <label className="block text-xs uppercase tracking-wide text-muted-foreground">
                        {tab === 'income_statement' || tab === 'cash_flow' || tab === 'equity' ? 'To' : 'As of'}
                    </label>
                    <input
                        type="date"
                        value={to}
                        onChange={(e) => setTo(e.target.value)}
                        className="mt-1 rounded-md border bg-background px-3 py-2 text-sm"
                    />
                </div>
                <button
                    type="button"
                    onClick={handleGenerate}
                    disabled={tb.isPending || bs.isPending || is_.isPending || cf.isPending || es.isPending}
                    className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                >
                    Generate
                </button>
            </div>

            <div className="mt-6">
                {tab === 'trial_balance'    && tb.data  && <TrialBalanceView report={tb.data} />}
                {tab === 'balance_sheet'    && bs.data  && <BalanceSheetView report={bs.data} />}
                {tab === 'income_statement' && is_.data && <IncomeStatementView report={is_.data} />}
                {tab === 'cash_flow'        && cf.data  && <CashFlowView report={cf.data} />}
                {tab === 'equity'           && es.data  && <EquityStatementView report={es.data} />}
            </div>

            {/* ── BIR-mandated Books of Accounts (RR 9-2009 / CAS) ────── */}
            <section className="mt-10 border-t pt-8">
                <h2 className="text-lg font-semibold">Books of Accounts</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    RR 9-2009 / CAS-mandated books. Each generation produces a PDF and records a run
                    in the audit log. Period below applies to all books.
                </p>

                <div className="mt-4 flex items-end gap-3">
                    <div>
                        <label className="block text-xs uppercase tracking-wide text-muted-foreground">From</label>
                        <input
                            type="date"
                            value={from}
                            onChange={(e) => setFrom(e.target.value)}
                            className="mt-1 rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </div>
                    <div>
                        <label className="block text-xs uppercase tracking-wide text-muted-foreground">To</label>
                        <input
                            type="date"
                            value={to}
                            onChange={(e) => setTo(e.target.value)}
                            className="mt-1 rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </div>
                </div>

                <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                    <BookTile
                        title="General Journal"
                        description="Chronological record of all journal entries."
                        rrCitation="RR 9-2009 §4 (a)"
                        hook={gj}
                        from={from}
                        to={to}
                        renderResult={(d) => (
                            <>
                                <div>{(d as JournalBookResult).entry_count} entries</div>
                                <div>Dr {formatPhp((d as JournalBookResult).total_debit)} / Cr {formatPhp((d as JournalBookResult).total_credit)}</div>
                            </>
                        )}
                    />
                    <BookTile
                        title="General Ledger"
                        description="Account-grouped running balances for the period."
                        rrCitation="RR 9-2009 §4 (b)"
                        hook={gl}
                        from={from}
                        to={to}
                        renderResult={(d) => (
                            <>
                                <div>{(d as JournalBookResult).entry_count} postings</div>
                            </>
                        )}
                    />
                    <BookTile
                        title="Sales Book"
                        description="Daily record of all sales invoices and official receipts."
                        rrCitation="RR 9-2009 §4 (c)"
                        hook={sb}
                        from={from}
                        to={to}
                        renderResult={(d) => (
                            <div>{(d as SubsidiaryBookResult).row_count} rows</div>
                        )}
                    />
                    <BookTile
                        title="Purchases Book"
                        description="Daily record of all purchases and vendor bills."
                        rrCitation="RR 9-2009 §4 (d)"
                        hook={pb}
                        from={from}
                        to={to}
                        renderResult={(d) => (
                            <div>{(d as SubsidiaryBookResult).row_count} rows</div>
                        )}
                    />
                    <BookTile
                        title="Cash Receipts Book"
                        description="All cash inflows — collections, deposits, advances."
                        rrCitation="RR 9-2009 §4 (e)"
                        hook={crb}
                        from={from}
                        to={to}
                        renderResult={(d) => (
                            <div>{(d as SubsidiaryBookResult).row_count} rows</div>
                        )}
                    />
                    <BookTile
                        title="Cash Disbursements Book"
                        description="All cash outflows — payments, payroll, purchases."
                        rrCitation="RR 9-2009 §4 (f)"
                        hook={cdb}
                        from={from}
                        to={to}
                        renderResult={(d) => (
                            <div>{(d as SubsidiaryBookResult).row_count} rows</div>
                        )}
                    />
                </div>
            </section>
        </div>
    );
}

function Tabs({ current, onChange }: { current: Tab; onChange: (t: Tab) => void }) {
    const tabs: Array<{ key: Tab; label: string }> = [
        { key: 'trial_balance',    label: 'Trial Balance' },
        { key: 'balance_sheet',    label: 'Balance Sheet' },
        { key: 'income_statement', label: 'Income Statement' },
        { key: 'cash_flow',        label: 'Cash Flow' },
        { key: 'equity',           label: 'Equity Statement' },
    ];
    return (
        <div className="mt-4 flex border-b">
            {tabs.map((t) => (
                <button
                    key={t.key}
                    type="button"
                    onClick={() => onChange(t.key)}
                    className={`-mb-px border-b-2 px-4 py-2 text-sm ${
                        current === t.key
                            ? 'border-primary font-medium text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground'
                    }`}
                >
                    {t.label}
                </button>
            ))}
        </div>
    );
}

function TrialBalanceView({ report }: { report: TrialBalance }) {
    return (
        <div className="overflow-hidden rounded-lg border bg-card">
            <div className="border-b bg-muted/30 px-4 py-2 text-sm">
                <strong>Trial Balance</strong> · {report.period.label}
                {!report.is_balanced && (
                    <span className="ml-3 rounded-md border border-destructive/40 bg-destructive/10 px-2 py-0.5 text-xs text-destructive">
                        ✗ Unbalanced — totals differ; investigate immediately.
                    </span>
                )}
            </div>
            <table className="w-full text-sm">
                <thead className="bg-muted/20 text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th className="px-4 py-2">Code</th>
                        <th className="px-4 py-2">Account</th>
                        <th className="px-4 py-2 text-right">Debit</th>
                        <th className="px-4 py-2 text-right">Credit</th>
                        <th className="px-4 py-2 text-right">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    {report.lines.map((l) => (
                        <tr key={l.account_id} className="border-t">
                            <td className="px-4 py-1.5 font-mono text-xs text-muted-foreground">
                                {l.account_code}
                            </td>
                            <td className="px-4 py-1.5">{l.account_name}</td>
                            <td className="px-4 py-1.5 text-right tabular-nums">{formatPhp(l.debit)}</td>
                            <td className="px-4 py-1.5 text-right tabular-nums">{formatPhp(l.credit)}</td>
                            <td className="px-4 py-1.5 text-right tabular-nums font-medium">
                                {formatPhp(l.balance)}
                            </td>
                        </tr>
                    ))}
                    <tr className="border-t-2 bg-muted/40 font-medium tabular-nums">
                        <td colSpan={2} className="px-4 py-2 text-right text-xs uppercase">
                            Totals
                        </td>
                        <td className="px-4 py-2 text-right">{formatPhp(report.total_debit)}</td>
                        <td className="px-4 py-2 text-right">{formatPhp(report.total_credit)}</td>
                        <td />
                    </tr>
                </tbody>
            </table>
        </div>
    );
}

function BalanceSheetView({ report }: { report: BalanceSheet }) {
    return (
        <div className="space-y-4">
            <div className="text-sm">
                <strong>Balance Sheet</strong> · {report.period.label}
                {!report.is_balanced && (
                    <span className="ml-3 rounded-md border border-destructive/40 bg-destructive/10 px-2 py-0.5 text-xs text-destructive">
                        ✗ Assets ≠ Liabilities + Equity
                    </span>
                )}
            </div>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <Section title="Assets">
                    <SubGroup label="Current assets" lines={report.assets.current} />
                    <SubGroup label="Non-current assets" lines={report.assets.non_current} />
                    <Total label="Total Assets" amount={report.assets.total} />
                </Section>

                <div className="space-y-4">
                    <Section title="Liabilities">
                        <SubGroup label="Current liabilities" lines={report.liabilities.current} />
                        <SubGroup label="Non-current liabilities" lines={report.liabilities.non_current} />
                        <Total label="Total Liabilities" amount={report.liabilities.total} />
                    </Section>

                    <Section title="Equity">
                        <SubGroup label="Equity components" lines={report.equity.lines} />
                        <Total label="Total Equity" amount={report.equity.total} />
                    </Section>
                </div>
            </div>
        </div>
    );
}

function IncomeStatementView({ report }: { report: IncomeStatement }) {
    return (
        <div className="rounded-lg border bg-card p-6">
            <div className="text-sm">
                <strong>Income Statement</strong> · {report.period.label}
            </div>
            <table className="mt-4 w-full text-sm">
                <tbody>
                    <SectionRow label="Revenue" amount={report.totals.revenue} bold />
                    {report.revenue.map((l) => (
                        <DetailRow key={l.account_id} l={l} />
                    ))}

                    <SectionRow label="Less: Cost of Sales" amount={report.totals.cost_of_sales} />
                    {report.cost_of_sales.map((l) => (
                        <DetailRow key={l.account_id} l={l} />
                    ))}

                    <SubtotalRow label="Gross Profit" amount={report.totals.gross_profit} />

                    <SectionRow label="Less: Operating Expenses" amount={report.totals.operating_expenses} />
                    {report.operating_expenses.map((l) => (
                        <DetailRow key={l.account_id} l={l} />
                    ))}

                    <SubtotalRow label="Operating Income" amount={report.totals.operating_income} />

                    {report.other_income.length > 0 && (
                        <>
                            <SectionRow label="Other Income" amount={report.totals.other_income} />
                            {report.other_income.map((l) => (
                                <DetailRow key={l.account_id} l={l} />
                            ))}
                        </>
                    )}
                    {report.other_expenses.length > 0 && (
                        <>
                            <SectionRow label="Less: Other Expenses" amount={report.totals.other_expenses} />
                            {report.other_expenses.map((l) => (
                                <DetailRow key={l.account_id} l={l} />
                            ))}
                        </>
                    )}

                    <SubtotalRow label="Income Before Tax" amount={report.totals.income_before_tax} />

                    <SectionRow label="Less: Income Tax" amount={report.totals.income_tax} />
                    {report.income_tax.map((l) => (
                        <DetailRow key={l.account_id} l={l} />
                    ))}

                    <TotalRow label="Net Income" amount={report.totals.net_income} />
                </tbody>
            </table>
        </div>
    );
}

function CashFlowView({ report }: { report: CashFlowStatement }) {
    return (
        <div className="rounded-lg border bg-card p-6">
            <div className="text-sm">
                <strong>Cash Flow Statement</strong> · {report.period.label}
                {!report.reconciles && (
                    <span className="ml-3 rounded-md border border-destructive/40 bg-destructive/10 px-2 py-0.5 text-xs text-destructive">
                        ✗ Beginning + net change ≠ ending cash
                    </span>
                )}
            </div>
            <table className="mt-4 w-full text-sm">
                <tbody>
                    <CashFlowSection title="Operating Activities" rows={report.operating_activities} total={report.total_operating} />
                    <CashFlowSection title="Investing Activities" rows={report.investing_activities} total={report.total_investing} />
                    <CashFlowSection title="Financing Activities" rows={report.financing_activities} total={report.total_financing} />

                    <TotalRow label="Net change in cash" amount={report.net_change} />
                    <DetailRow l={{ account_id: 'beg', account_code: '', account_name: 'Cash, beginning', amount: report.beginning_cash }} />
                    <TotalRow label="Cash, ending" amount={report.ending_cash} />
                </tbody>
            </table>
        </div>
    );
}

/* ── Shared row/section primitives ────────────────────────────────────── */

function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <section className="rounded-lg border bg-card p-4">
            <h3 className="text-base font-semibold">{title}</h3>
            <div className="mt-2 space-y-3">{children}</div>
        </section>
    );
}

function SubGroup({ label, lines }: { label: string; lines: Array<{ account_id: string; account_name: string; amount: string }> }) {
    return (
        <div>
            <div className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</div>
            <table className="mt-1 w-full text-sm">
                <tbody>
                    {lines.map((l) => (
                        <tr key={l.account_id}>
                            <td className="py-0.5 pl-2 text-muted-foreground">{l.account_name}</td>
                            <td className="py-0.5 text-right tabular-nums">{formatPhp(l.amount)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function Total({ label, amount }: { label: string; amount: string }) {
    return (
        <div className="flex items-center justify-between border-t pt-2 text-sm font-semibold tabular-nums">
            <span>{label}</span>
            <span>{formatPhp(amount)}</span>
        </div>
    );
}

function SectionRow({ label, amount, bold }: { label: string; amount: string; bold?: boolean }) {
    return (
        <tr className={`border-t ${bold ? 'font-medium' : 'text-muted-foreground'}`}>
            <td className="px-2 py-1.5">{label}</td>
            <td className="px-2 py-1.5 text-right tabular-nums">{formatPhp(amount)}</td>
        </tr>
    );
}

function DetailRow({ l }: { l: { account_id: string; account_code?: string; account_name: string; amount: string } }) {
    return (
        <tr>
            <td className="px-2 py-0.5 pl-6 text-xs text-muted-foreground">{l.account_name}</td>
            <td className="px-2 py-0.5 text-right text-xs tabular-nums text-muted-foreground">
                {formatPhp(l.amount)}
            </td>
        </tr>
    );
}

function SubtotalRow({ label, amount }: { label: string; amount: string }) {
    return (
        <tr className="border-t bg-muted/30 font-medium">
            <td className="px-2 py-1.5">{label}</td>
            <td className="px-2 py-1.5 text-right tabular-nums">{formatPhp(amount)}</td>
        </tr>
    );
}

function TotalRow({ label, amount }: { label: string; amount: string }) {
    return (
        <tr className="border-y-2 bg-muted/50 text-base font-semibold">
            <td className="px-2 py-2">{label}</td>
            <td className="px-2 py-2 text-right tabular-nums">{formatPhp(amount)}</td>
        </tr>
    );
}

function CashFlowSection({
    title,
    rows,
    total,
}: {
    title: string;
    rows: Array<{ description: string; amount: string }>;
    total: string;
}) {
    return (
        <>
            <SectionRow label={title} amount="" bold />
            {rows.map((r, i) => (
                <tr key={i}>
                    <td className="px-2 py-0.5 pl-6 text-xs text-muted-foreground">{r.description}</td>
                    <td className="px-2 py-0.5 text-right text-xs tabular-nums text-muted-foreground">
                        {formatPhp(r.amount)}
                    </td>
                </tr>
            ))}
            <SubtotalRow label={`Net cash from ${title.toLowerCase()}`} amount={total} />
        </>
    );
}

function EquityStatementView({ report }: { report: EquityStatement }) {
    return (
        <div className="rounded-lg border bg-card p-6">
            <div className="text-sm">
                <strong>Statement of Changes in Equity</strong> · {report.period}
            </div>
            <table className="mt-4 w-full text-sm">
                <tbody>
                    <tr className="text-xs uppercase tracking-wide text-muted-foreground">
                        <td className="px-2 py-1">Component</td>
                        <td className="px-2 py-1 text-right">Beginning</td>
                        <td className="px-2 py-1 text-right">Movement</td>
                        <td className="px-2 py-1 text-right">Ending</td>
                    </tr>
                    <tr className="border-t">
                        <td className="px-2 py-1.5 pl-4 text-muted-foreground">
                            {report.account_count} equity account{report.account_count !== 1 ? 's' : ''}
                        </td>
                        <td className="px-2 py-1.5 text-right tabular-nums">{formatPhp(report.total_beginning)}</td>
                        <td className="px-2 py-1.5 text-right tabular-nums">{formatPhp(report.total_movement)}</td>
                        <td className="px-2 py-1.5 text-right tabular-nums">{formatPhp(report.total_ending)}</td>
                    </tr>
                    <tr className="border-t text-muted-foreground">
                        <td className="px-2 py-1.5 pl-4">Net income for period</td>
                        <td />
                        <td className="px-2 py-1.5 text-right tabular-nums">{formatPhp(report.net_income_for_period)}</td>
                        <td />
                    </tr>
                    <TotalRow label="Total Equity" amount={report.total_equity} />
                </tbody>
            </table>
            {report.pdf_path && (
                <div className="mt-4">
                    <a
                        href={`/api/v1/storage/${report.pdf_path}`}
                        target="_blank"
                        rel="noreferrer"
                        className="rounded-md border px-3 py-1.5 text-xs hover:bg-accent"
                    >
                        Download PDF
                    </a>
                </div>
            )}
        </div>
    );
}

interface BookHook {
    mutate: (input: { from: string; to: string }) => void;
    data?: unknown;
    isPending: boolean;
    error: unknown;
}

function BookTile({
    title,
    description,
    rrCitation,
    hook,
    from,
    to,
    renderResult,
}: {
    title: string;
    description: string;
    rrCitation: string;
    hook: BookHook;
    from: string;
    to: string;
    renderResult: (d: unknown) => React.ReactNode;
}) {
    return (
        <div className="rounded-lg border bg-card p-4">
            <h3 className="text-sm font-medium">{title}</h3>
            <p className="mt-1 text-xs text-muted-foreground">{description}</p>
            <p className="mt-0.5 font-mono text-[10px] text-muted-foreground">{rrCitation}</p>

            {hook.data && (
                <div className="mt-3 rounded-md border border-emerald-200 bg-emerald-50 p-2 text-xs text-emerald-900">
                    <div className="font-medium">Generated</div>
                    {renderResult(hook.data)}
                    {(hook.data as { pdf_path?: string | null }).pdf_path && (
                        <a
                            href={`/api/v1/storage/${(hook.data as { pdf_path: string }).pdf_path}`}
                            target="_blank"
                            rel="noreferrer"
                            className="mt-1 inline-block text-emerald-800 underline"
                        >
                            Download PDF
                        </a>
                    )}
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
                onClick={() => hook.mutate({ from, to })}
                disabled={hook.isPending}
                className="mt-3 w-full rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
            >
                {hook.isPending ? 'Generating…' : `Generate ${title}`}
            </button>
        </div>
    );
}
