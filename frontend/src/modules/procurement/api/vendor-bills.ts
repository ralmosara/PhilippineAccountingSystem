import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { api } from '@/shared/lib/api';

export interface Vendor {
    id: string;
    vendor_no: string;
    registered_name: string;
    tin: string | null;
    is_vat_registered: boolean;
    default_atc_code: string | null;
}

export interface VendorBillLine {
    id?: string;
    line_no?: number;
    description: string;
    amount: string;
    atc_code?: string | null;
    expense_account_id: string;
}

export interface VendorBill {
    id: string;
    vendor_id: string;
    vendor_name?: string;
    purchase_order_id: string | null;
    vendor_invoice_no: string;
    bill_date: string;
    due_date: string | null;
    subtotal: string;
    vat_input: string;
    withholding_amount: string;
    total: string;
    status: 'draft' | 'posted' | 'paid' | 'voided';
    posted_at: string | null;
    journal_entry_id: string | null;
    form_2307_id: string | null;
    lines: VendorBillLine[];
}

export function useVendors(query: string = '') {
    return useQuery({
        queryKey: ['procurement', 'vendors', query],
        queryFn: async () => {
            const res = await api.get<{ data: Vendor[] }>('/vendors', { params: { q: query, per_page: 100 } });
            return res.data.data;
        },
        staleTime: 60_000,
    });
}

export function useVendorBills(
    params: {
        status?: string | undefined;
        from?: string | undefined;
        to?: string | undefined;
        vendor_id?: string | undefined;
    } = {},
) {
    return useQuery({
        queryKey: ['procurement', 'vendor-bills', params],
        queryFn: async () => {
            const res = await api.get<{ data: VendorBill[] }>('/vendor-bills', { params });
            return res.data.data;
        },
    });
}

export function useVendorBill(id: string | null) {
    return useQuery({
        queryKey: ['procurement', 'vendor-bills', id],
        enabled: !!id,
        queryFn: async () => {
            const res = await api.get<{ data: VendorBill }>(`/vendor-bills/${id}`);
            return res.data.data;
        },
    });
}

export interface PostVendorBillInput {
    vendor_id: string;
    purchase_order_id?: string | undefined;
    vendor_invoice_no: string;
    bill_date: string;
    due_date?: string | undefined;
    ap_account_id: string;
    vat_input_account_id?: string | undefined;
    withholding_payable_account_id?: string | undefined;
    lines: Array<{
        description: string;
        amount: string;
        atc_code?: string | null | undefined;
        expense_account_id: string;
    }>;
}

export function usePostVendorBill() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (input: PostVendorBillInput) => {
            const res = await api.post<VendorBill>('/vendor-bills/post', input);
            return res.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['procurement'] }),
    });
}

export function useIssue2307ForBill() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (billId: string) => {
            const res = await api.post(`/vendor-bills/${billId}/issue-2307`);
            return res.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['procurement'] }),
    });
}
