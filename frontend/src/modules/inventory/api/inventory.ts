import { useQuery } from '@tanstack/react-query';

import { api } from '@/shared/lib/api';

export interface Item {
    id: string;
    sku: string;
    name: string;
    description: string | null;
    costing_method: 'moving_average' | 'fifo' | 'specific_id';
    is_inventory: boolean;
    is_active: boolean;
    /** Aggregated across all warehouses; backend joins inventory.stock_balances. */
    total_quantity: string;
    /** Computed weighted average across warehouses. */
    moving_avg_cost: string;
    total_value: string;
}

export interface Warehouse {
    id: string;
    code: string;
    name: string;
    branch_id: string;
}

export interface StockMovement {
    id: string;
    item_id: string;
    item_sku?: string;
    item_name?: string;
    warehouse_id: string;
    warehouse_name?: string;
    movement_type: 'receipt' | 'issue' | 'transfer_out' | 'transfer_in' | 'adjustment';
    quantity: string;
    unit_cost: string;
    /** Total cost of the movement (qty × unit_cost), positive in/negative out. */
    total_cost: string;
    /** MA cost AFTER this movement was applied — what the next issue draws against. */
    resulting_ma_cost: string;
    /** Quantity on hand after this movement. */
    resulting_quantity: string;
    source_doc_type: string | null;
    source_doc_id: string | null;
    moved_at: string;
    memo: string | null;
}

export function useItems(params: { q?: string | undefined; active_only?: boolean | undefined } = {}) {
    return useQuery({
        queryKey: ['inventory', 'items', params],
        queryFn: async () => {
            const res = await api.get<{ data: Item[] }>('/items', { params: { ...params, per_page: 200 } });
            return res.data.data;
        },
    });
}

export function useItem(id: string | null) {
    return useQuery({
        queryKey: ['inventory', 'items', id],
        enabled: !!id,
        queryFn: async () => {
            const res = await api.get<{ data: Item }>(`/items/${id}`);
            return res.data.data;
        },
    });
}

export function useStockMovements(
    params: { item_id?: string | undefined; warehouse_id?: string | undefined; from?: string | undefined; to?: string | undefined } = {},
) {
    return useQuery({
        queryKey: ['inventory', 'stock-movements', params],
        queryFn: async () => {
            const res = await api.get<{ data: StockMovement[] }>('/stock-movements', { params });
            return res.data.data;
        },
    });
}

export function useWarehouses() {
    return useQuery({
        queryKey: ['inventory', 'warehouses'],
        queryFn: async () => {
            const res = await api.get<{ data: Warehouse[] }>('/warehouses');
            return res.data.data;
        },
        staleTime: 5 * 60_000,
    });
}

export const MOVEMENT_TYPE_LABELS: Record<StockMovement['movement_type'], string> = {
    receipt:        'Receipt',
    issue:          'Issue',
    transfer_out:   'Transfer out',
    transfer_in:    'Transfer in',
    adjustment:     'Adjustment',
};
