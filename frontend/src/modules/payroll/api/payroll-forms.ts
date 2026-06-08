import { useMutation, useQueryClient } from '@tanstack/react-query';

import { api } from '@/shared/lib/api';

/**
 * Payroll-driven BIR forms.
 *
 *   1601-C — Monthly Remittance Return of Income Taxes Withheld on
 *            Compensation. Filed monthly with BIR; aggregates all WHT
 *            booked across the period's approved payroll runs.
 *
 *   2316   — Certificate of Compensation Payment / Tax Withheld. Issued
 *            ANNUALLY to each employee. Driven from the year's payroll
 *            runs; one PDF per employee.
 */
export interface BirForm1601C {
    id: string;
    period_from: string;
    period_to: string;
    total_compensation: string;
    total_withholding: string;
    pdf_path: string | null;
    status: 'draft' | 'generated' | 'filed';
}

export interface BirForm2316Batch {
    id: string;
    year: number;
    employee_count: number;
    generated_at: string;
    pdf_zip_path: string | null;
}

export function useGenerateForm1601C() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (input: { period_from: string; period_to: string }) => {
            const res = await api.post<BirForm1601C>('/payroll/forms/1601c/generate', input);
            return res.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['payroll'] }),
    });
}

export function useGenerateForm2316Batch() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (input: { year: number }) => {
            const res = await api.post<BirForm2316Batch>('/payroll/forms/2316/generate', input);
            return res.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['payroll'] }),
    });
}

/**
 * 13th month pay run — PD 851.
 * Sums annual basic salary per employee, divides by 12, applies RA 10963 ₱90k exemption.
 * Idempotent: returns the existing run if one already exists for the year.
 */
export interface ThirteenthMonthRun {
    id: string;
    run_no: string;
    run_type: '13th_month';
    status: string;
    period_start: string;
    period_end: string;
    total_gross: string;
    total_net: string;
    payslip_count: number;
}

export function useGenerate13thMonthRun() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (input: { year: number }) => {
            const res = await api.post<ThirteenthMonthRun>(
                '/payroll-runs/13th-month/generate',
                input,
            );
            return res.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['payroll'] }),
    });
}

/**
 * Statutory remittance generation — SSS R-3, PhilHealth RF-1, Pag-IBIG MCRF.
 * Each agency's controller dispatches into the same Action with a different
 * StatutoryAgency enum, producing a CSV body the operator uploads to the
 * agency's portal.
 */
export interface StatutoryRemittanceResult {
    id: string;
    agency: 'sss' | 'philhealth' | 'pagibig';
    form_code: string;
    period_from: string;
    period_to: string;
    line_count: number;
    total_remittance: string;
    storage_path: string;
    file_body: string;
}

function makeRemittanceHook(endpoint: string) {
    return () => {
        const qc = useQueryClient();
        return useMutation({
            mutationFn: async (input: { period_from: string; period_to: string }) => {
                const res = await api.post<StatutoryRemittanceResult>(endpoint, input);
                return res.data;
            },
            onSuccess: () => qc.invalidateQueries({ queryKey: ['payroll'] }),
        });
    };
}

export const useGenerateSssR3       = makeRemittanceHook('/payroll/remittances/sss-r3/generate');
export const useGeneratePhilHealth  = makeRemittanceHook('/payroll/remittances/philhealth-rf1/generate');
export const useGeneratePagIbig     = makeRemittanceHook('/payroll/remittances/pagibig-mcrf/generate');
