import { useMutation, useQueryClient } from '@tanstack/react-query';

import { api } from '@/shared/lib/api';

/**
 * Annual Inventory List — RMC 57-2015 mandated submission.
 *
 * Filed within 30 days after the end of the calendar/fiscal year. Lists every
 * inventoriable item with on-hand quantity + total value as of year-end.
 * BIR accepts a CSV; the backend Action renders one row per (item, warehouse).
 */
export interface InventoryListResult {
    id: string;
    as_of: string;
    line_count: number;
    total_quantity: string;
    total_value: string;
    storage_path: string;
    csv_body: string;
}

export function useGenerateInventoryList() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (input: { as_of: string }) => {
            const res = await api.post<InventoryListResult>('/inventory/list/generate', input);
            return res.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['inventory'] }),
    });
}
