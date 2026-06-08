import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { api } from '@/shared/lib/api';

export interface FiscalPeriod {
    id: string;
    fiscal_year_id: string;
    fiscal_year_label?: string;
    period_number: number;
    starts_on: string;
    ends_on: string;
    locked_at: string | null;
    locked_by: string | null;
}

export function useFiscalPeriods(params: { year?: number; locked?: boolean } = {}) {
    return useQuery({
        queryKey: ['accounting', 'fiscal-periods', params],
        queryFn: async () => {
            const res = await api.get<{ data: FiscalPeriod[] }>('/fiscal-periods', { params });
            return res.data.data;
        },
    });
}

export function useLockFiscalPeriod() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async ({ id }: { id: string }) => {
            const res = await api.post<FiscalPeriod>(`/fiscal-periods/${id}/lock`);
            return res.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['accounting', 'fiscal-periods'] }),
    });
}
