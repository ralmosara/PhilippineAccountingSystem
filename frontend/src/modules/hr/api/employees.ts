import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { api } from '@/shared/lib/api';

export interface Employee {
    id: string;
    employee_no: string;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    full_name: string;
    email: string | null;
    hired_on: string | null;
    separated_on: string | null;
    employment_status: 'probationary' | 'regular' | 'contract' | 'project' | 'consultant';
    department_id: string | null;
    position_id: string | null;
    is_active: boolean;
    /** PII — masked as ***1234 for roles without hr.employees.manage */
    tin: string | null;
    sss_no: string | null;
    philhealth_no: string | null;
    pagibig_no: string | null;
    created_at: string | null;
}

export interface EmployeeInput {
    first_name: string;
    last_name: string;
    middle_name?: string;
    email?: string;
    hired_on: string;
    employment_status?: string;
    department_id?: string;
    position_id?: string;
    tin?: string;
    sss_no?: string;
    philhealth_no?: string;
    pagibig_no?: string;
}

export function useEmployees(params: { active_only?: boolean; search?: string; department_id?: string } = {}) {
    return useQuery({
        queryKey: ['employees', params],
        queryFn: () =>
            api
                .get<{ data: Employee[] }>('/employees', { params })
                .then((r) => r.data.data),
    });
}

export function useEmployee(id: string | null) {
    return useQuery({
        queryKey: ['employees', id],
        queryFn: () =>
            api.get<{ data: Employee }>(`/employees/${id}`).then((r) => r.data.data),
        enabled: !!id,
    });
}

export function useCreateEmployee() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: (data: EmployeeInput) =>
            api.post<{ data: Employee }>('/employees', data).then((r) => r.data.data),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['employees'] }),
    });
}

export interface Department {
    id: string;
    code: string;
    name: string;
    parent_id?: string | null;
    is_active?: boolean;
}

export function useDepartments() {
    return useQuery({
        queryKey: ['departments'],
        queryFn: () =>
            api.get<{ data: Department[] }>('/departments').then((r) => r.data.data),
    });
}

export function useCreateDepartment() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: (input: { code: string; name: string; parent_id?: string }) =>
            api.post<{ data: Department }>('/departments', input).then((r) => r.data.data),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['departments'] }),
    });
}

export function useDeactivateDepartment() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: (id: string) => api.delete(`/departments/${id}`).then((r) => r.data),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['departments'] }),
    });
}

export interface Position {
    id: string;
    code: string;
    title: string;
    department_id: string | null;
    salary_grade_min: string | null;
    salary_grade_max: string | null;
    is_active: boolean;
}

export function usePositions(departmentId?: string) {
    return useQuery({
        queryKey: ['positions', departmentId ?? null],
        queryFn: () =>
            api
                .get<{ data: Position[] }>('/positions', {
                    params: departmentId ? { department_id: departmentId } : {},
                })
                .then((r) => r.data.data),
    });
}
