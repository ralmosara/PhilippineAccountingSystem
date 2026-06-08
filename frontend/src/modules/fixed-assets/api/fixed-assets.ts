import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { api } from '@/shared/lib/api';

export interface FixedAsset {
    id: string;
    company_id: string;
    branch_id: string | null;
    cost_center_id: string | null;
    asset_no: string;
    name: string;
    description: string | null;
    category: string;
    category_label: string;
    acquisition_date: string;
    acquisition_cost: string;
    salvage_value: string;
    useful_life_months: number;
    depreciation_method: string;
    depreciation_method_label: string;
    accumulated_depreciation: string;
    book_value: string;
    asset_account_id: string | null;
    accum_depr_account_id: string | null;
    depr_expense_account_id: string | null;
    status: 'active' | 'disposed' | 'fully_depreciated';
    disposed_at: string | null;
    disposal_proceeds: string | null;
    disposal_gain_loss: string | null;
    disposal_journal_entry_id: string | null;
    created_at: string;
    updated_at: string;
}

export interface DepreciationEntry {
    id: string;
    fixed_asset_id: string;
    period_year: number;
    period_month: number;
    period_label: string;
    depreciation_amount: string;
    accumulated_after: string;
    book_value_after: string;
    journal_entry_id: string | null;
    posted_at: string | null;
}

export interface RegisterAssetInput {
    name: string;
    description?: string;
    category: string;
    acquisition_date: string;
    acquisition_cost: string;
    salvage_value?: string;
    useful_life_months: number;
    depreciation_method: string;
    branch_id?: string;
    cost_center_id?: string;
    asset_account_id?: string;
    accum_depr_account_id?: string;
    depr_expense_account_id?: string;
}

export interface ComputeDepreciationInput {
    year: number;
    month: number;
}

export interface ComputeDepreciationResult {
    assets_processed: number;
    total_depreciation: string;
    year: number;
    month: number;
}

export interface DisposeAssetInput {
    proceeds: string;
    disposal_date: string;
    cash_account_id: string;
    gain_loss_account_id: string;
}

export function useFixedAssets(params?: { status?: string; category?: string }) {
    return useQuery({
        queryKey: ['fixed-assets', params],
        queryFn: async () => {
            const res = await api.get<{ data: FixedAsset[] }>('/fixed-assets', { params });
            return res.data.data;
        },
    });
}

export function useFixedAsset(id: string | null) {
    return useQuery({
        queryKey: ['fixed-assets', id],
        enabled: !!id,
        queryFn: async () => {
            const res = await api.get<{ data: FixedAsset }>(`/fixed-assets/${id}`);
            return res.data.data;
        },
    });
}

export function useRegisterAsset() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (input: RegisterAssetInput) => {
            const res = await api.post<{ data: FixedAsset }>('/fixed-assets', input);
            return res.data.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['fixed-assets'] }),
    });
}

export function useComputeMonthlyDepreciation() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (input: ComputeDepreciationInput) => {
            const res = await api.post<{ data: ComputeDepreciationResult }>(
                '/fixed-assets/depreciation/compute',
                input,
            );
            return res.data.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['fixed-assets'] }),
    });
}

export function useDisposeAsset(id: string) {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (input: DisposeAssetInput) => {
            const res = await api.post<{ data: FixedAsset }>(
                `/fixed-assets/${id}/dispose`,
                input,
            );
            return res.data.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['fixed-assets'] }),
    });
}
