import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { api } from '@/shared/lib/api';

export interface Vendor {
    id: string;
    vendor_no: string;
    registered_name: string;
    trade_name: string | null;
    tin: string | null;
    is_vat_registered: boolean;
    is_government_supplier: boolean;
    is_top_withholding_agent: boolean;
    default_atc_code: string | null;
    default_withholding_rate: string | null;
    payment_terms_days: number;
    email: string | null;
    phone: string | null;
    is_active: boolean;
    created_at: string | null;
}

export interface VendorInput {
    registered_name: string;
    tin?: string;
    is_vat_registered?: boolean;
    is_government_supplier?: boolean;
    is_top_withholding_agent?: boolean;
    default_atc_code?: string;
    default_withholding_rate?: string;
    payment_terms_days?: number;
    email?: string;
}

export function useVendors(params: { search?: string; active_only?: boolean } = {}) {
    return useQuery({
        queryKey: ['vendors', params],
        queryFn: () =>
            api
                .get<{ data: Vendor[] }>('/vendors', { params })
                .then((r) => r.data.data),
    });
}

export function useVendor(id: string | null) {
    return useQuery({
        queryKey: ['vendors', id],
        queryFn: () =>
            api.get<{ data: Vendor }>(`/vendors/${id}`).then((r) => r.data.data),
        enabled: !!id,
    });
}

export function useCreateVendor() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: (data: VendorInput) =>
            api.post<{ data: Vendor }>('/vendors', data).then((r) => r.data.data),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['vendors'] }),
    });
}
