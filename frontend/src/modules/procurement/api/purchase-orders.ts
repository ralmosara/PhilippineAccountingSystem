import { useQuery } from '@tanstack/react-query';

import { api } from '@/shared/lib/api';

export interface PurchaseOrderLine {
    line_no: number;
    description: string;
    quantity: string;
    received_quantity: string;
    billed_quantity: string;
    unit_price: string;
    line_total: string;
    item_id: string | null;
    expense_account_id: string | null;
}

export interface PurchaseOrder {
    id: string;
    po_no: string;
    vendor_id: string;
    order_date: string | null;
    expected_delivery: string | null;
    currency: string;
    subtotal: string;
    vat_amount: string;
    total: string;
    status: 'draft' | 'approved' | 'sent' | 'partial' | 'fulfilled' | 'cancelled';
    approved_at: string | null;
    remarks: string | null;
    lines: PurchaseOrderLine[];
}

export function usePurchaseOrders(params: { vendor_id?: string; status?: string } = {}) {
    return useQuery({
        queryKey: ['purchase-orders', params],
        queryFn: () =>
            api
                .get<{ data: PurchaseOrder[] }>('/purchase-orders', { params })
                .then((r) => r.data.data),
    });
}

export function usePurchaseOrder(id: string | null) {
    return useQuery({
        queryKey: ['purchase-orders', id],
        queryFn: () =>
            api
                .get<{ data: PurchaseOrder }>(`/purchase-orders/${id}`)
                .then((r) => r.data.data),
        enabled: !!id,
    });
}
