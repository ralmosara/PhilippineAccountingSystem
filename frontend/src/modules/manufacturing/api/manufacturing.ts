import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { api } from '@/shared/lib/api';

// ── Interfaces ────────────────────────────────────────────────────────────────

export interface BomLine {
    id: string;
    component_item_id: string;
    component_name: string;
    quantity_per_batch: string;
    unit_of_measure: string;
    notes: string | null;
}

export interface Bom {
    id: string;
    company_id: string;
    item_id: string;
    item_name: string;
    code: string;
    name: string;
    version: string;
    status: 'draft' | 'active' | 'superseded';
    standard_batch_size: string;
    labor_cost_per_batch: string;
    overhead_cost_per_batch: string;
    notes: string | null;
    lines?: BomLine[];
}

export interface WorkOrderLine {
    id: string;
    component_item_id: string;
    component_name: string;
    quantity_required: string;
    quantity_consumed: string;
    unit_cost: string;
    total_cost: string;
}

export interface WorkOrder {
    id: string;
    company_id: string;
    bom_id: string;
    work_order_no: string;
    status: 'draft' | 'released' | 'in_progress' | 'completed' | 'cancelled';
    status_label: string;
    status_badge: string;
    quantity_to_produce: string;
    quantity_produced: string;
    scheduled_start: string | null;
    scheduled_end: string | null;
    actual_start: string | null;
    actual_end: string | null;
    warehouse_id: string | null;
    wip_account_id: string | null;
    finished_goods_account_id: string | null;
    raw_materials_account_id: string | null;
    total_material_cost: string;
    total_labor_cost: string;
    total_overhead_cost: string;
    total_production_cost: string;
    journal_entry_id: string | null;
    lines?: WorkOrderLine[];
}

export interface CreateBomInput {
    item_id: string;
    item_name: string;
    code: string;
    name: string;
    version?: string;
    standard_batch_size: string;
    labor_cost_per_batch: string;
    overhead_cost_per_batch: string;
    notes?: string | null;
    lines: Array<{
        component_item_id: string;
        component_name: string;
        quantity_per_batch: string;
        unit_of_measure: string;
        notes?: string | null;
    }>;
}

export interface CreateWorkOrderInput {
    bom_id: string;
    quantity_to_produce: string;
    scheduled_start?: string | null;
    scheduled_end?: string | null;
    warehouse_id?: string | null;
    wip_account_id?: string | null;
    finished_goods_account_id?: string | null;
    raw_materials_account_id?: string | null;
}

// ── BOM Queries ───────────────────────────────────────────────────────────────

export function useBoms(params: { status?: string; q?: string } = {}) {
    return useQuery({
        queryKey: ['manufacturing', 'boms', params],
        queryFn: async () => {
            const res = await api.get<{ data: Bom[] }>('/boms', { params: { ...params, per_page: 100 } });
            return res.data.data;
        },
    });
}

export function useBom(id: string | null) {
    return useQuery({
        queryKey: ['manufacturing', 'boms', id],
        enabled: !!id,
        queryFn: async () => {
            const res = await api.get<{ data: Bom }>(`/boms/${id}`);
            return res.data.data;
        },
    });
}

export function useCreateBom() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (input: CreateBomInput) => {
            const res = await api.post<{ data: Bom }>('/boms', input);
            return res.data.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['manufacturing', 'boms'] }),
    });
}

export function useUpdateBom(id: string) {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (input: Partial<CreateBomInput>) => {
            const res = await api.put<{ data: Bom }>(`/boms/${id}`, input);
            return res.data.data;
        },
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['manufacturing', 'boms'] });
            qc.invalidateQueries({ queryKey: ['manufacturing', 'boms', id] });
        },
    });
}

// ── Work Order Queries ────────────────────────────────────────────────────────

export function useWorkOrders(params: { status?: string; bom_id?: string } = {}) {
    return useQuery({
        queryKey: ['manufacturing', 'work-orders', params],
        queryFn: async () => {
            const res = await api.get<{ data: WorkOrder[] }>('/work-orders', { params: { ...params, per_page: 100 } });
            return res.data.data;
        },
    });
}

export function useWorkOrder(id: string | null) {
    return useQuery({
        queryKey: ['manufacturing', 'work-orders', id],
        enabled: !!id,
        queryFn: async () => {
            const res = await api.get<{ data: WorkOrder }>(`/work-orders/${id}`);
            return res.data.data;
        },
    });
}

export function useCreateWorkOrder() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (input: CreateWorkOrderInput) => {
            const res = await api.post<{ data: WorkOrder }>('/work-orders', input);
            return res.data.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['manufacturing', 'work-orders'] }),
    });
}

export function useStartWorkOrder(id: string) {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async () => {
            const res = await api.post<{ data: WorkOrder }>(`/work-orders/${id}/start`);
            return res.data.data;
        },
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['manufacturing', 'work-orders'] });
            qc.invalidateQueries({ queryKey: ['manufacturing', 'work-orders', id] });
        },
    });
}

export function useCompleteProductionRun(id: string) {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async ({ quantity_produced }: { quantity_produced: string }) => {
            const res = await api.post<{ data: WorkOrder }>(`/work-orders/${id}/complete`, { quantity_produced });
            return res.data.data;
        },
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['manufacturing', 'work-orders'] });
            qc.invalidateQueries({ queryKey: ['manufacturing', 'work-orders', id] });
            // Invalidate inventory since stock balances change
            qc.invalidateQueries({ queryKey: ['inventory'] });
        },
    });
}

export function useCancelWorkOrder(id: string) {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async () => {
            const res = await api.post<{ data: WorkOrder }>(`/work-orders/${id}/cancel`);
            return res.data.data;
        },
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['manufacturing', 'work-orders'] });
            qc.invalidateQueries({ queryKey: ['manufacturing', 'work-orders', id] });
        },
    });
}
