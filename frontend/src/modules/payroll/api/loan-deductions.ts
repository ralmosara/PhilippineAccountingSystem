import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { api } from '@/shared/lib/api';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface LoanDeduction {
    id: string;
    employee_id: string;
    loan_type: 'sss_salary_loan' | 'hdmf_mpl' | 'hdmf_housing';
    loan_type_label: string;
    loan_reference: string;
    original_amount: string;
    outstanding_balance: string;
    monthly_amortization: string;
    started_on: string;
    ends_on: string | null;
    is_active: boolean;
    notes: string | null;
    created_at: string | null;
}

export interface LoanDeductionInput {
    employee_id: string;
    loan_type: 'sss_salary_loan' | 'hdmf_mpl' | 'hdmf_housing';
    loan_reference: string;
    original_amount: string;
    monthly_amortization: string;
    started_on: string;
    ends_on?: string;
    notes?: string;
}

// ---------------------------------------------------------------------------
// Hooks
// ---------------------------------------------------------------------------

export function useLoanDeductions(employeeId?: string) {
    return useQuery({
        queryKey: ['loan-deductions', employeeId ?? null],
        queryFn: () =>
            api
                .get<{ data: LoanDeduction[] }>('/loan-deductions', {
                    params: employeeId ? { employee_id: employeeId } : undefined,
                })
                .then((r) => r.data.data),
    });
}

export function useRegisterLoanDeduction() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: (input: LoanDeductionInput) =>
            api
                .post<{ data: LoanDeduction }>('/loan-deductions', input)
                .then((r) => r.data.data),
        onSuccess: (_data, vars) => {
            qc.invalidateQueries({ queryKey: ['loan-deductions'] });
            qc.invalidateQueries({ queryKey: ['loan-deductions', vars.employee_id] });
        },
    });
}

export function useSettleLoanDeduction() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: (id: string) =>
            api
                .post<{ data: LoanDeduction }>(`/loan-deductions/${id}/settle`)
                .then((r) => r.data.data),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['loan-deductions'] });
        },
    });
}
