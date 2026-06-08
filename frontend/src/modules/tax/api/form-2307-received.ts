import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { api } from '@/shared/lib/api';

export interface Form2307Received {
    id: string;
    payor: {
        tin: string;
        registered_name: string;
        branch_code: string;
        address: string | null;
    };
    certificate_no: string | null;
    atc_code: string;
    period_from: string;
    period_to: string;
    income_payment: string;
    tax_withheld: string;
    entry_method: 'manual' | 'pdf_upload' | 'csv_import';
    status: 'draft' | 'recorded' | 'claimed' | 'rejected';
    claimed_in_bir_form_id: string | null;
    rejection_reason: string | null;
}

export function useForm2307Received(
    params: {
        year?: number | undefined;
        quarter?: number | undefined;
        status?: string | undefined;
        payor_tin?: string | undefined;
    } = {},
) {
    return useQuery({
        queryKey: ['tax', 'form-2307-received', params],
        queryFn: async () => {
            const res = await api.get<{ data: Form2307Received[] }>('/tax/form-2307-received', {
                params,
            });
            return res.data.data;
        },
    });
}

export interface BulkImportReport {
    summary: { total: number; successful: number; duplicates: number; failed: number };
    successful: Array<{ row_no: number; cert_id: string; payor_tin: string; tax_withheld: string }>;
    duplicates: Array<{ row_no: number; error_type: string; message: string }>;
    failed: Array<{ row_no: number; error_type: string; message: string }>;
}

export function useBulkImport2307() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (csv: string) => {
            const res = await api.post<BulkImportReport>(
                '/tax/form-2307-received/bulk-import',
                { csv },
                // Accept 201 (some success) AND 422 (all duplicate/failed) — the
                // report shape is the same; we want both surfaces in the UI.
                { validateStatus: (s) => s === 201 || s === 422 },
            );
            return res.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['tax', 'form-2307-received'] }),
    });
}

export function useReject2307Received() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async ({ id, reason }: { id: string; reason: string }) => {
            await api.post(`/tax/form-2307-received/${id}/reject`, { reason });
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['tax', 'form-2307-received'] }),
    });
}
