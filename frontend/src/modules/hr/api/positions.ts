import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { api } from '@/shared/lib/api';

export interface Position {
    id: string;
    code: string;
    title: string;
    department_id: string | null;
    salary_grade_min: string | null;
    salary_grade_max: string | null;
    is_active: boolean;
}

export interface PositionInput {
    code: string;
    title: string;
    department_id?: string;
    salary_grade_min?: string;
    salary_grade_max?: string;
}

export function usePositionsList() {
    return useQuery({
        queryKey: ['positions'],
        queryFn: () =>
            api.get<{ data: Position[] }>('/positions').then((r) => r.data.data),
    });
}

export function useCreatePosition() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: (input: PositionInput) =>
            api.post<{ data: Position }>('/positions', input).then((r) => r.data.data),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['positions'] }),
    });
}

export function useDeactivatePosition() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: (id: string) => api.delete(`/positions/${id}`).then((r) => r.data),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['positions'] }),
    });
}
