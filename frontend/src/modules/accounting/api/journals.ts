import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { api } from '@/shared/lib/api';

export interface Account {
    id: string;
    code: string;
    name: string;
    type: 'asset' | 'liability' | 'equity' | 'revenue' | 'expense' | 'contra_asset';
    normal_balance: 'debit' | 'credit';
    is_postable: boolean;
    is_active: boolean;
}

export interface JournalLineInput {
    line_no: number;
    account_id: string;
    description: string;
    debit: string;     // BCMath string
    credit: string;
    tax_code_id?: string | null;
    project_id?: string | null;
    cost_center_id?: string | null;
}

export interface JournalEntry {
    id: string;
    doc_no: string;
    entry_date: string;
    memo: string | null;
    source: 'manual' | 'sales' | 'purchase' | 'payroll' | 'recurring';
    status: 'draft' | 'posted' | 'reversed';
    posted_at: string | null;
    posted_by: string | null;
    fiscal_period_id: string;
    lines: Array<{
        id: string;
        line_no: number;
        account_id: string;
        account_code: string;
        account_name: string;
        description: string | null;
        debit: string;
        credit: string;
    }>;
}

export function useAccounts() {
    return useQuery({
        queryKey: ['accounting', 'accounts'],
        queryFn: async () => {
            const res = await api.get<{ data: Account[] }>('/accounts', {
                params: { is_postable: true, is_active: true, per_page: 500 },
            });
            return res.data.data;
        },
        staleTime: 5 * 60_000,
    });
}

export function useJournalEntries(
    params: { status?: string | undefined; from?: string | undefined; to?: string | undefined } = {},
) {
    return useQuery({
        queryKey: ['accounting', 'journals', params],
        queryFn: async () => {
            const res = await api.get<{ data: JournalEntry[] }>('/journals', { params });
            return res.data.data;
        },
    });
}

export function useJournalEntry(id: string | null) {
    return useQuery({
        queryKey: ['accounting', 'journals', id],
        enabled: !!id,
        queryFn: async () => {
            const res = await api.get<{ data: JournalEntry }>(`/journals/${id}`);
            return res.data.data;
        },
    });
}

export interface SaveJournalEntryInput {
    id?: string | undefined;
    entry_date: string;
    memo?: string | undefined;
    document_series_id: string;
    lines: JournalLineInput[];
}

export function useSaveJournalEntry() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (input: SaveJournalEntryInput) => {
            const res = input.id
                ? await api.put<JournalEntry>(`/journals/${input.id}`, input)
                : await api.post<JournalEntry>('/journals', input);
            return res.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['accounting', 'journals'] }),
    });
}

export function usePostJournalEntry() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (id: string) => {
            const res = await api.post<JournalEntry>(`/journals/${id}/post`);
            return res.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['accounting', 'journals'] }),
    });
}

export function useReverseJournalEntry() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async ({ id, reason }: { id: string; reason: string }) => {
            const res = await api.post<JournalEntry>(`/journals/${id}/reverse`, { reason });
            return res.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['accounting', 'journals'] }),
    });
}
