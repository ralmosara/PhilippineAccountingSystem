import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { api } from '@/shared/lib/api';
import { type Customer } from './sales-invoices';

export interface CustomerInput {
    registered_name: string;
    tin?: string | undefined;
    is_vat_registered?: boolean | undefined;
    is_government?: boolean | undefined;
    is_senior_citizen?: boolean | undefined;
    is_pwd?: boolean | undefined;
    email?: string | undefined;
    payment_terms_days?: number | undefined;
}

export function useCustomer(id: string | null) {
    return useQuery({
        queryKey: ['sales', 'customers', id],
        enabled: !!id,
        queryFn: async () => {
            const res = await api.get<{ data: Customer }>(`/customers/${id}`);
            return res.data.data;
        },
    });
}

export interface CustomerArAging {
    customer_id: string;
    as_of: string;
    buckets: {
        current: string;
        d1_30: string;
        d31_60: string;
        d61_90: string;
        d91_plus: string;
    };
    total: string;
    unpaid_invoice_count: number;
}

/**
 * Authoritative AR-aging snapshot from the backend (replaces the client-side
 * approximation in CustomerDetailPage). Refetches every 60s while the page
 * is mounted so a freshly-posted OR or new SI shows up promptly.
 */
export function useCustomerArAging(customerId: string | null, asOf?: string | undefined) {
    return useQuery({
        queryKey: ['sales', 'customers', customerId, 'ar-aging', asOf],
        enabled: !!customerId,
        queryFn: async () => {
            const res = await api.get<CustomerArAging>(
                `/customers/${customerId}/ar-aging`,
                { params: asOf ? { as_of: asOf } : {} },
            );
            return res.data;
        },
        staleTime: 60_000,
    });
}

export function useSaveCustomer() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async ({ id, input }: { id?: string | undefined; input: CustomerInput }) => {
            const res = id
                ? await api.put<Customer>(`/customers/${id}`, input)
                : await api.post<Customer>('/customers', input);
            return res.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['sales', 'customers'] }),
    });
}
