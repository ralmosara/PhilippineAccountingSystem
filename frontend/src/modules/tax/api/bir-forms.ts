import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { api } from '@/shared/lib/api';

export interface BirFormLine {
    line_code: string;
    description: string;
    amount: string;
}

export interface BirForm {
    id: string;
    form_type: string;
    period: { from: string | null; to: string | null; year: number; month: number | null; quarter: number | null };
    status: 'draft' | 'generated' | 'filed';
    tax_due: string;
    tax_paid: string;
    generated_at: string | null;
    filed_at: string | null;
    bir_filing_ref: string | null;
    pdf_path: string | null;
    xml_path: string | null;
    dat_path: string | null;
    lines: BirFormLine[];
    data: Record<string, unknown>;
}

export function useBirForms(params: { form_type?: string; year?: number; status?: string } = {}) {
    return useQuery({
        queryKey: ['tax', 'bir-forms', params],
        queryFn: async () => {
            const res = await api.get<{ data: BirForm[] }>('/tax-forms', { params });
            return res.data.data;
        },
    });
}

export interface QuarterlyItrInput {
    year: number;
    quarter: 1 | 2 | 3;
    use_osd?: boolean;
    elect_flat_8pct?: boolean;
    personal_exemption?: string;
    creditable_wt?: string;
}

export interface AnnualItrInput {
    year: number;
    use_osd?: boolean;
    elect_flat_8pct?: boolean;
    personal_exemption?: string;
    prior_excess_credits?: string;
    creditable_wt?: string;
    quarterly_payments?: string;
}

export function useGenerate1701Q() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (input: QuarterlyItrInput) => {
            const res = await api.post<BirForm>('/tax-forms/1701q/generate', input);
            return res.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['tax'] }),
    });
}

export function useGenerate1702Q() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (input: QuarterlyItrInput) => {
            const res = await api.post<BirForm>('/tax-forms/1702q/generate', input);
            return res.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['tax'] }),
    });
}

export function useGenerate1701() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (i: AnnualItrInput) => {
            const res = await api.post<BirForm>('/tax-forms/1701/generate', i);
            return res.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['tax'] }),
    });
}

export function useGenerate1702RT() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (i: AnnualItrInput) => {
            const res = await api.post<BirForm>('/tax-forms/1702rt/generate', i);
            return res.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['tax'] }),
    });
}
