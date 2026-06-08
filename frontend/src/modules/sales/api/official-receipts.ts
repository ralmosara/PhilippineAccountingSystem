import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { api } from '@/shared/lib/api';

export type PaymentMethod = 'cash' | 'check' | 'bank_transfer' | 'credit_card' | 'gcash' | 'maya';

export interface OfficialReceipt {
    id: string;
    company_id: string;
    customer_id: string;
    customer_name?: string;
    sales_invoice_id: string | null;
    sales_invoice_doc_no?: string | null;
    document_series_id: string;
    doc_no: string;
    received_date: string;
    amount: string;
    payment_method: PaymentMethod;
    reference_no: string | null;
    remarks: string | null;
    posted_at: string | null;
    posted_by: string | null;
    journal_entry_id: string | null;
}

export interface IssueOrInput {
    customer_id: string;
    sales_invoice_id?: string | undefined;
    document_series_id: string;
    received_date: string;
    amount: string;
    cash_account_id: string;
    ar_account_id: string;
    payment_method: PaymentMethod;
    reference_no?: string | undefined;
    remarks?: string | undefined;
}

export function useOfficialReceipts(params: {
    from?: string | undefined;
    to?: string | undefined;
    customer_id?: string | undefined;
    sales_invoice_id?: string | undefined;
} = {}) {
    return useQuery({
        queryKey: ['sales', 'official-receipts', params],
        queryFn: async () => {
            const res = await api.get<{ data: OfficialReceipt[] }>('/official-receipts', { params });
            return res.data.data;
        },
    });
}

export function useIssueOfficialReceipt() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (input: IssueOrInput) => {
            const res = await api.post<OfficialReceipt>('/official-receipts/issue', input);
            return res.data;
        },
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['sales'] });
        },
    });
}

export const PAYMENT_METHOD_LABELS: Record<PaymentMethod, string> = {
    cash:          'Cash',
    check:         'Check',
    bank_transfer: 'Bank transfer',
    credit_card:   'Credit / debit card',
    gcash:         'GCash',
    maya:          'Maya / PayMaya',
};
