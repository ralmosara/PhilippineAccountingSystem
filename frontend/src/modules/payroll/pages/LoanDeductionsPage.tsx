import { useState } from 'react';

import { DataTable, type Column } from '@/shared/components/DataTable';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';
import { formatPhp } from '@/shared/lib/money';

import {
    useLoanDeductions,
    useRegisterLoanDeduction,
    useSettleLoanDeduction,
    type LoanDeduction,
    type LoanDeductionInput,
} from '../api/loan-deductions';

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------

export function LoanDeductionsPage() {
    const user = useAuthStore((s) => s.user);
    const canManage = hasPermission(user, 'payroll.runs.compute');

    const [employeeFilter, setEmployeeFilter] = useState('');
    const [showForm, setShowForm] = useState(false);

    const { data: loans, isLoading } = useLoanDeductions(employeeFilter || undefined);
    const register = useRegisterLoanDeduction();
    const settle = useSettleLoanDeduction();

    // Confirm-settle state
    const [pendingSettle, setPendingSettle] = useState<string | null>(null);

    function handleSettle(id: string) {
        settle.mutate(id, { onSuccess: () => setPendingSettle(null) });
    }

    const columns: Column<LoanDeduction>[] = [
        {
            key: 'employee_id',
            header: 'Employee ID',
            render: (r) => (
                <span className="font-mono text-xs" title={r.employee_id}>
                    {r.employee_id.slice(0, 8)}…
                </span>
            ),
        },
        {
            key: 'loan_type',
            header: 'Loan Type',
            render: (r) => <LoanTypeBadge loanType={r.loan_type} label={r.loan_type_label} />,
        },
        {
            key: 'loan_reference',
            header: 'Reference',
            render: (r) => <span className="font-mono text-xs">{r.loan_reference}</span>,
        },
        {
            key: 'original_amount',
            header: 'Original',
            align: 'right',
            numeric: true,
            render: (r) => formatPhp(r.original_amount),
        },
        {
            key: 'outstanding_balance',
            header: 'Outstanding',
            align: 'right',
            numeric: true,
            render: (r) => formatPhp(r.outstanding_balance),
        },
        {
            key: 'monthly_amortization',
            header: 'Monthly Amort.',
            align: 'right',
            numeric: true,
            render: (r) => formatPhp(r.monthly_amortization),
        },
        {
            key: 'period',
            header: 'Period',
            render: (r) => (
                <span className="text-xs">
                    {r.started_on} – {r.ends_on ?? 'open'}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            render: (r) => <StatusBadge isActive={r.is_active} />,
        },
        {
            key: 'actions',
            header: '',
            render: (r) =>
                r.is_active && canManage ? (
                    pendingSettle === r.id ? (
                        <span className="flex items-center gap-1">
                            <button
                                type="button"
                                onClick={() => handleSettle(r.id)}
                                disabled={settle.isPending}
                                className="rounded-md bg-destructive px-2 py-0.5 text-xs text-white hover:opacity-90 disabled:opacity-50"
                            >
                                Confirm
                            </button>
                            <button
                                type="button"
                                onClick={() => setPendingSettle(null)}
                                className="rounded-md border px-2 py-0.5 text-xs hover:bg-accent"
                            >
                                Cancel
                            </button>
                        </span>
                    ) : (
                        <button
                            type="button"
                            onClick={() => setPendingSettle(r.id)}
                            className="rounded-md border px-2 py-0.5 text-xs hover:bg-accent"
                        >
                            Settle
                        </button>
                    )
                ) : null,
        },
    ];

    return (
        <div className="container py-8">
            {/* Header */}
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">Loan Deductions</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        SSS salary loans and Pag-IBIG (HDMF) multi-purpose / housing loans
                        deducted from payroll runs.
                    </p>
                </div>
                {canManage && (
                    <button
                        type="button"
                        onClick={() => setShowForm((v) => !v)}
                        className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90"
                    >
                        {showForm ? '− Cancel' : '+ Register Loan'}
                    </button>
                )}
            </header>

            {/* Register form (collapsible) */}
            {showForm && canManage && (
                <RegisterLoanForm
                    onSuccess={() => setShowForm(false)}
                    register={register}
                />
            )}

            {/* Employee filter */}
            <div className="mt-4 flex items-center gap-3">
                <input
                    type="text"
                    placeholder="Filter by employee UUID"
                    value={employeeFilter}
                    onChange={(e) => setEmployeeFilter(e.target.value)}
                    className="w-80 rounded-md border bg-background px-3 py-2 text-sm font-mono placeholder:font-sans placeholder:text-muted-foreground"
                />
                {employeeFilter && (
                    <button
                        type="button"
                        onClick={() => setEmployeeFilter('')}
                        className="text-sm text-muted-foreground hover:text-foreground"
                    >
                        Clear
                    </button>
                )}
            </div>

            {/* Error feedback */}
            {(settle.isError || register.isError) && (
                <div className="mt-3 rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                    {(
                        (settle.error || register.error) as {
                            response?: { data?: { message?: string } };
                        }
                    )?.response?.data?.message ?? 'Operation failed.'}
                </div>
            )}

            {/* Table */}
            <div className="mt-6">
                <DataTable
                    columns={columns}
                    rows={loans}
                    rowKey={(r) => r.id}
                    isLoading={isLoading}
                    emptyState="No loan deductions found. Register one using the button above."
                />
            </div>
        </div>
    );
}

// ---------------------------------------------------------------------------
// Register form
// ---------------------------------------------------------------------------

interface RegisterLoanFormProps {
    onSuccess: () => void;
    register: ReturnType<typeof useRegisterLoanDeduction>;
}

function RegisterLoanForm({ onSuccess, register }: RegisterLoanFormProps) {
    const [form, setForm] = useState<Partial<LoanDeductionInput>>({
        loan_type: 'sss_salary_loan',
    });

    function set<K extends keyof LoanDeductionInput>(key: K, value: LoanDeductionInput[K]) {
        setForm((prev) => ({ ...prev, [key]: value }));
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        register.mutate(form as LoanDeductionInput, { onSuccess });
    }

    return (
        <form
            onSubmit={handleSubmit}
            className="mt-4 rounded-lg border bg-card p-5 shadow-sm"
        >
            <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                Register New Loan Deduction
            </h2>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Field label="Employee ID (UUID)">
                    <input
                        required
                        type="text"
                        placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                        value={form.employee_id ?? ''}
                        onChange={(e) => set('employee_id', e.target.value)}
                        className="w-full rounded-md border bg-background px-3 py-2 font-mono text-sm"
                    />
                </Field>

                <Field label="Loan Type">
                    <select
                        required
                        value={form.loan_type ?? 'sss_salary_loan'}
                        onChange={(e) =>
                            set(
                                'loan_type',
                                e.target.value as LoanDeductionInput['loan_type'],
                            )
                        }
                        className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                    >
                        <option value="sss_salary_loan">SSS Salary Loan</option>
                        <option value="hdmf_mpl">HDMF Multi-Purpose Loan</option>
                        <option value="hdmf_housing">HDMF Housing Loan</option>
                    </select>
                </Field>

                <Field label="Loan Reference No.">
                    <input
                        required
                        type="text"
                        maxLength={50}
                        placeholder="Agency reference number"
                        value={form.loan_reference ?? ''}
                        onChange={(e) => set('loan_reference', e.target.value)}
                        className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                    />
                </Field>

                <Field label="Original Amount (PHP)">
                    <input
                        required
                        type="number"
                        min="0.01"
                        step="0.01"
                        placeholder="0.00"
                        value={form.original_amount ?? ''}
                        onChange={(e) => set('original_amount', e.target.value)}
                        className="w-full rounded-md border bg-background px-3 py-2 text-right text-sm tabular-nums"
                    />
                </Field>

                <Field label="Monthly Amortization (PHP)">
                    <input
                        required
                        type="number"
                        min="0.01"
                        step="0.01"
                        placeholder="0.00"
                        value={form.monthly_amortization ?? ''}
                        onChange={(e) => set('monthly_amortization', e.target.value)}
                        className="w-full rounded-md border bg-background px-3 py-2 text-right text-sm tabular-nums"
                    />
                </Field>

                <Field label="Started On">
                    <input
                        required
                        type="date"
                        value={form.started_on ?? ''}
                        onChange={(e) => set('started_on', e.target.value)}
                        className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                    />
                </Field>

                <Field label="Ends On (optional)">
                    <input
                        type="date"
                        value={form.ends_on ?? ''}
                        onChange={(e) => set('ends_on', e.target.value || undefined)}
                        className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                    />
                </Field>

                <Field label="Notes (optional)" className="sm:col-span-2">
                    <textarea
                        maxLength={500}
                        rows={2}
                        placeholder="Optional notes…"
                        value={form.notes ?? ''}
                        onChange={(e) => set('notes', e.target.value || undefined)}
                        className="w-full resize-none rounded-md border bg-background px-3 py-2 text-sm"
                    />
                </Field>
            </div>

            <div className="mt-4 flex justify-end gap-3">
                <button
                    type="submit"
                    disabled={register.isPending}
                    className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                >
                    {register.isPending ? 'Registering…' : 'Register Loan'}
                </button>
            </div>
        </form>
    );
}

// ---------------------------------------------------------------------------
// Sub-components
// ---------------------------------------------------------------------------

function Field({
    label,
    children,
    className,
}: {
    label: string;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <div className={className}>
            <label className="mb-1 block text-xs uppercase tracking-wide text-muted-foreground">
                {label}
            </label>
            {children}
        </div>
    );
}

function LoanTypeBadge({
    loanType,
    label,
}: {
    loanType: LoanDeduction['loan_type'];
    label: string;
}) {
    const styles: Record<LoanDeduction['loan_type'], string> = {
        sss_salary_loan: 'bg-blue-100 text-blue-800',
        hdmf_mpl:        'bg-teal-100 text-teal-800',
        hdmf_housing:    'bg-purple-100 text-purple-800',
    };

    return (
        <span
            className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${styles[loanType]}`}
        >
            {label}
        </span>
    );
}

function StatusBadge({ isActive }: { isActive: boolean }) {
    return isActive ? (
        <span className="inline-block rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">
            Active
        </span>
    ) : (
        <span className="inline-block rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
            Settled
        </span>
    );
}
