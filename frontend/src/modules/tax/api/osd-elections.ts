import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { api } from '@/shared/lib/api';

export interface OsdElection {
    id: string;
    company_id: string;
    fiscal_year: number;
    taxpayer_type: 'individual' | 'corporate';
    regime: 'itemized' | 'osd' | 'flat_8pct';
    declared_in: {
        form_type: string;
        quarter: number | null;
        bir_form_id: string | null;
    };
    locked_at: string;
    locked_by: string;
    replaces_id: string | null;
    superseded_at: string | null;
    supersede_reason: string | null;
    is_active: boolean;
}

interface OsdElectionListParams {
    fiscal_year?: number;
    taxpayer_type?: 'individual' | 'corporate';
    active_only?: boolean;
}

export function useOsdElections(params: OsdElectionListParams = {}) {
    return useQuery({
        queryKey: ['tax', 'osd-elections', params],
        queryFn: async () => {
            const res = await api.get<{ data: OsdElection[] }>('/tax/osd-elections', { params });
            return res.data.data;
        },
    });
}

export function useSupersedeOsdElection() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (input: {
            election_id: string;
            new_regime: OsdElection['regime'];
            reason: string;
        }) => {
            const res = await api.post<OsdElection>(
                `/tax/osd-elections/${input.election_id}/supersede`,
                { new_regime: input.new_regime, reason: input.reason },
            );
            return res.data;
        },
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['tax', 'osd-elections'] });
        },
    });
}

export const REGIME_LABELS: Record<OsdElection['regime'], string> = {
    itemized: 'Itemized Deductions',
    osd: 'OSD (40%)',
    flat_8pct: '8% Flat Rate',
};
