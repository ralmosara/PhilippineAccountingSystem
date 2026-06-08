import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { api } from '@/shared/lib/api';

export interface Customer {
    id: string;
    customer_no: string;
    registered_name: string;
    tin: string | null;
    is_vat_registered: boolean;
    is_government: boolean;
    is_senior_citizen: boolean;
    is_pwd: boolean;
    email: string | null;
    payment_terms_days: number;
}

export interface SalesInvoiceLine {
    id?: string;
    line_no?: number;
    description: string;
    quantity: string;
    unit_price: string;
    tax_kind?: 'vat_output' | 'vat_zero' | 'vat_exempt';
    tax_code_id?: string | null;
    revenue_account_id: string;
    discount_pct?: string;
    vat_amount?: string;
    line_total?: string;
}

export interface SalesInvoice {
    id: string;
    company_id: string;
    customer_id: string;
    customer_name?: string;
    document_series_id: string;
    doc_no: string;
    doc_kind: 'cash' | 'charge';
    invoice_date: string;
    due_date: string | null;
    currency: string;
    fx_rate: string;
    subtotal: string;
    vatable_sales: string;
    vat_zero_rated_sales: string;
    vat_exempt_sales: string;
    vat_amount: string;
    discount_amount: string;
    senior_pwd_discount: string;
    withheld_vat: string;
    total: string;
    posted_at: string | null;
    posted_by: string | null;
    journal_entry_id: string | null;
    voided_at: string | null;
    void_reason: string | null;
    voided_by: string | null;
    lines: SalesInvoiceLine[];
}

/** Customer list — used by the customer picker and the SI list filter. */
export function useCustomers(query: string = '') {
    return useQuery({
        queryKey: ['sales', 'customers', query],
        queryFn: async () => {
            const res = await api.get<{ data: Customer[] }>('/customers', {
                params: { q: query, per_page: 100 },
            });
            return res.data.data;
        },
        staleTime: 60_000,
    });
}

export function useSalesInvoices(params: {
    status?: 'draft' | 'posted' | 'voided' | undefined;
    from?: string | undefined;
    to?: string | undefined;
    customer_id?: string | undefined;
} = {}) {
    return useQuery({
        queryKey: ['sales', 'invoices', params],
        queryFn: async () => {
            const res = await api.get<{ data: SalesInvoice[] }>('/sales-invoices', { params });
            return res.data.data;
        },
    });
}

export function useSalesInvoice(id: string | null) {
    return useQuery({
        queryKey: ['sales', 'invoices', id],
        enabled: !!id,
        queryFn: async () => {
            const res = await api.get<{ data: SalesInvoice }>(`/sales-invoices/${id}`);
            return res.data.data;
        },
    });
}

export interface IssueInvoiceInput {
    customer_id: string;
    document_series_id: string;
    invoice_date: string;
    due_date?: string | undefined;
    doc_kind?: 'cash' | 'charge' | undefined;
    currency?: string | undefined;
    ar_account_id: string;
    vat_payable_account_id: string;
    lines: Array<{
        description: string;
        quantity: string;
        unit_price: string;
        tax_kind?: 'vat_output' | 'vat_zero' | 'vat_exempt' | undefined;
        tax_code_id?: string | null | undefined;
        revenue_account_id: string;
        discount_pct?: string | undefined;
    }>;
}

export function useIssueSalesInvoice() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (input: IssueInvoiceInput) => {
            const res = await api.post<SalesInvoice>('/sales-invoices/issue', input);
            return res.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['sales'] }),
    });
}

export function useVoidSalesInvoice() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async ({ id, reason }: { id: string; reason: string }) => {
            const res = await api.post<SalesInvoice>(`/sales-invoices/${id}/void`, { reason });
            return res.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['sales'] }),
    });
}

export function useRetransmitToEis() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (id: string) => {
            await api.post(`/sales-invoices/${id}/eis/transmit`);
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['sales'] }),
    });
}
