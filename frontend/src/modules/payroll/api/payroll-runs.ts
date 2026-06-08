import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { api } from '@/shared/lib/api';

export interface PayslipLine {
    id: string;
    employee_id: string;
    employee_name?: string;
    gross: string;
    sss_ee: string;
    phic_ee: string;
    hdmf_ee: string;
    withholding_tax: string;
    net_pay: string;
}

export interface PayrollRun {
    id: string;
    period_start: string;
    period_end: string;
    status: 'draft' | 'computed' | 'approved' | 'paid';
    computed_at: string | null;
    approved_at: string | null;
    approved_by: string | null;
    total_gross: string;
    total_net: string;
    total_sss: string;
    total_phic: string;
    total_hdmf: string;
    total_wht: string;
    payslip_lines: PayslipLine[];
}

export function usePayrollRuns() {
    return useQuery({
        queryKey: ['payroll', 'runs'],
        queryFn: async () => {
            const res = await api.get<{ data: PayrollRun[] }>('/payroll-runs');
            return res.data.data;
        },
    });
}

export function usePayrollRun(id: string | null) {
    return useQuery({
        queryKey: ['payroll', 'runs', id],
        enabled: !!id,
        queryFn: async () => {
            const res = await api.get<{ data: PayrollRun }>(`/payroll-runs/${id}`);
            return res.data.data;
        },
    });
}

export function useComputePayrollRun() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (input: { period_start: string; period_end: string }) => {
            const res = await api.post<PayrollRun>('/payroll-runs/compute', input);
            return res.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['payroll'] }),
    });
}

export function useApprovePayrollRun() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (id: string) => {
            const res = await api.post<PayrollRun>(`/payroll-runs/${id}/approve`);
            return res.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['payroll'] }),
    });
}

export type SeparationReason =
    | 'resigned'
    | 'terminated'
    | 'redundancy'
    | 'retrenchment'
    | 'closure'
    | 'illness'
    | 'other';

export interface FinalPayInput {
    employee_id: string;
    separation_date: string;
    separation_reason: SeparationReason;
}

export interface PayrollRunLine {
    line_no: number;
    line_type: string;
    code: string;
    description: string;
    amount: string;
}

export interface FinalPayPayslip {
    id: string;
    employee_id: string;
    gross_compensation: string;
    sss_ee: string;
    phic_ee: string;
    hdmf_ee: string;
    withholding_tax: string;
    net_pay: string;
    lines: PayrollRunLine[] | null;
}

export interface FinalPayRun {
    id: string;
    run_no: string;
    run_type: string;
    payroll_period_id: string;
    status: string;
    computed_at: string | null;
    approved_at: string | null;
    approved_by: string | null;
    paid_at: string | null;
    journal_entry_id: string | null;
    payslip_count: number;
    totals: {
        gross: string;
        sss_ee: string;
        phic_ee: string;
        hdmf_ee: string;
        withholding_tax: string;
        net_pay: string;
    } | null;
    payslips: FinalPayPayslip[] | null;
}

export function useComputeFinalPayRun() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (input: FinalPayInput) => {
            const res = await api.post<{ data: FinalPayRun }>('/payroll-runs/final-pay/compute', input);
            return res.data.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['payroll'] }),
    });
}
