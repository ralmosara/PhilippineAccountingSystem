import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { api } from '@/shared/lib/api';

export interface CompensationPackage {
    id: string;
    employee_id: string;
    effective_from: string;
    effective_to: string | null;
    basic_monthly: string;
    basic_daily: string | null;
    working_days_per_month: number | null;
    hours_per_day: number | null;
    hourly_rate: string | null;
    is_minimum_wage_earner: boolean;
    created_at: string | null;
}

export interface CompensationPackageInput {
    employee_id: string;
    effective_from: string;
    effective_to?: string;
    basic_monthly: string;
    basic_daily?: string;
    working_days_per_month?: number;
    hours_per_day?: number;
    hourly_rate?: string;
    is_minimum_wage_earner?: boolean;
}

export function useCompensationPackages(employeeId: string | null) {
    return useQuery({
        queryKey: ['compensation-packages', employeeId],
        enabled: !!employeeId,
        queryFn: () =>
            api
                .get<{ data: CompensationPackage[] }>('/compensation-packages', {
                    params: { employee_id: employeeId },
                })
                .then((r) => r.data.data),
    });
}

export function useCreateCompensationPackage() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: (input: CompensationPackageInput) =>
            api
                .post<{ data: CompensationPackage }>('/compensation-packages', input)
                .then((r) => r.data.data),
        onSuccess: (_data, vars) => {
            qc.invalidateQueries({ queryKey: ['compensation-packages', vars.employee_id] });
        },
    });
}

export function useCloseCompensationPackage() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: (id: string) =>
            api.delete(`/compensation-packages/${id}`).then((r) => r.data),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['compensation-packages'] }),
    });
}
