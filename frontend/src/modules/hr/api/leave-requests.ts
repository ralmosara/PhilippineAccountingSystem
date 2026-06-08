import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { api } from '@/shared/lib/api';

export interface LeaveRequest {
    id: string;
    company_id: string;
    employee_id: string;
    leave_type: 'sick' | 'vacation' | 'emergency' | 'maternity' | 'paternity' | 'solo_parent' | 'bereavement';
    start_date: string;
    end_date: string;
    days_requested: string;
    reason: string | null;
    status: 'pending' | 'approved' | 'rejected' | 'cancelled';
    approved_by: string | null;
    approved_at: string | null;
    rejection_reason: string | null;
    created_at: string;
}

export interface LeaveRequestInput {
    employee_id: string;
    leave_type: LeaveRequest['leave_type'];
    start_date: string;
    end_date: string;
    reason?: string;
}

export interface LeaveRequestParams {
    employee_id?: string;
    status?: LeaveRequest['status'];
    leave_type?: LeaveRequest['leave_type'];
    page?: number;
}

export function useLeaveRequests(params: LeaveRequestParams = {}) {
    return useQuery({
        queryKey: ['leave-requests', params],
        queryFn: () =>
            api
                .get<{ data: LeaveRequest[] }>('/leave-requests', { params })
                .then((r) => r.data.data),
    });
}

export function useLeaveRequest(id: string | null) {
    return useQuery({
        queryKey: ['leave-requests', id],
        queryFn: () =>
            api
                .get<{ data: LeaveRequest }>(`/leave-requests/${id}`)
                .then((r) => r.data.data),
        enabled: !!id,
    });
}

export function useSubmitLeaveRequest() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: (data: LeaveRequestInput) =>
            api
                .post<{ data: LeaveRequest }>('/leave-requests', data)
                .then((r) => r.data.data),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['leave-requests'] }),
    });
}

export function useApproveLeaveRequest(id: string) {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: () =>
            api
                .post<{ data: LeaveRequest }>(`/leave-requests/${id}/approve`)
                .then((r) => r.data.data),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['leave-requests'] }),
    });
}

export function useRejectLeaveRequest(id: string) {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: (rejection_reason: string) =>
            api
                .post<{ data: LeaveRequest }>(`/leave-requests/${id}/reject`, { rejection_reason })
                .then((r) => r.data.data),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['leave-requests'] }),
    });
}
