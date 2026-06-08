import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { api } from '@/shared/lib/api';

export interface Project {
    id: string;
    code: string;
    name: string;
    billing_type: 'fixed_price' | 'time_and_materials' | 'retainer';
    status: 'draft' | 'active' | 'on_hold' | 'completed' | 'cancelled';
    contract_value: string | null;
    budget_hours: string | null;
    customer_id: string | null;
    wip_account_id: string | null;
    revenue_account_id: string | null;
    starts_on: string | null;
    ends_on: string | null;
    completed_at: string | null;
    timesheet_entry_count?: number;
}

export interface TimesheetEntry {
    id: string;
    project_id: string;
    employee_id: string;
    work_date: string;
    hours: string;
    billable_rate: string;
    billable_amount: string;
    description: string | null;
    is_billed: boolean;
}

export interface WipEntry {
    id: string;
    project_id: string;
    journal_entry_id: string | null;
    period_from: string;
    period_to: string;
    total_hours: string;
    total_cost: string;
    total_billed: string;
    recognized_revenue: string;
    status: 'draft' | 'posted';
    posted_at: string | null;
    posted_by: string | null;
}

export function useProjects() {
    return useQuery({
        queryKey: ['projects'],
        queryFn: () =>
            api.get<{ data: Project[] }>('/projects').then((r) => r.data.data),
    });
}

export function useProject(id: string) {
    return useQuery({
        queryKey: ['projects', id],
        queryFn: () =>
            api.get<{ data: Project }>(`/projects/${id}`).then((r) => r.data.data),
        enabled: !!id,
    });
}

export function useCreateProject() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: (d: Partial<Project>) =>
            api.post<{ data: Project }>('/projects', d).then((r) => r.data.data),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['projects'] }),
    });
}

export function useUpdateProject(id: string) {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: (d: Partial<Project>) =>
            api.patch<{ data: Project }>(`/projects/${id}`, d).then((r) => r.data.data),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['projects'] }),
    });
}

export function useProjectTimesheets(projectId: string) {
    return useQuery({
        queryKey: ['projects', projectId, 'timesheets'],
        queryFn: () =>
            api
                .get<{ data: TimesheetEntry[] }>(`/projects/${projectId}/timesheets`)
                .then((r) => r.data.data),
        enabled: !!projectId,
    });
}

export function useLogTimesheet() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: (d: { project_id: string } & Partial<TimesheetEntry>) =>
            api
                .post<{ data: TimesheetEntry }>(`/projects/${d.project_id}/timesheets`, d)
                .then((r) => r.data.data),
        onSuccess: (_data, vars) => {
            qc.invalidateQueries({ queryKey: ['projects', vars.project_id, 'timesheets'] });
            qc.invalidateQueries({ queryKey: ['projects'] });
        },
    });
}

export function useRecognizeWip(projectId: string) {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: (d: {
            period_from: string;
            period_to: string;
            wip_account_id: string;
            revenue_account_id: string;
        }) =>
            api
                .post<{ data: WipEntry }>(`/projects/${projectId}/recognize-wip`, d)
                .then((r) => r.data.data),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['projects'] }),
    });
}

export function useCloseProject(projectId: string) {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: () =>
            api
                .post<{ data: Project }>(`/projects/${projectId}/close`)
                .then((r) => r.data.data),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['projects'] }),
    });
}
