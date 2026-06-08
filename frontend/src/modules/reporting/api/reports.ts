import { useMutation, useQueryClient } from '@tanstack/react-query';

import { api } from '@/shared/lib/api';

export interface TrialBalanceLine {
    account_id: string;
    account_code: string;
    account_name: string;
    account_type: string;
    normal_balance: 'debit' | 'credit';
    debit: string;
    credit: string;
    balance: string;
}

export interface TrialBalance {
    company_id: string;
    period: { label: string; from: string; to: string };
    lines: TrialBalanceLine[];
    total_debit: string;
    total_credit: string;
    is_balanced: boolean;
}

export interface FinancialStatementLine {
    account_id: string;
    account_code: string;
    account_name: string;
    amount: string;
}

export interface IncomeStatement {
    company_id: string;
    period: { label: string; from: string; to: string };
    revenue: FinancialStatementLine[];
    cost_of_sales: FinancialStatementLine[];
    operating_expenses: FinancialStatementLine[];
    other_income: FinancialStatementLine[];
    other_expenses: FinancialStatementLine[];
    income_tax: FinancialStatementLine[];
    totals: {
        revenue: string;
        cost_of_sales: string;
        operating_expenses: string;
        other_income: string;
        other_expenses: string;
        income_tax: string;
        gross_profit: string;
        operating_income: string;
        income_before_tax: string;
        net_income: string;
    };
}

export interface BalanceSheet {
    company_id: string;
    period: { label: string };
    assets: { current: FinancialStatementLine[]; non_current: FinancialStatementLine[]; total: string };
    liabilities: { current: FinancialStatementLine[]; non_current: FinancialStatementLine[]; total: string };
    equity: { lines: FinancialStatementLine[]; total: string };
    is_balanced: boolean;
}

export interface CashFlowStatement {
    company_id: string;
    period: { label: string };
    operating_activities: Array<{ description: string; amount: string }>;
    investing_activities: Array<{ description: string; amount: string }>;
    financing_activities: Array<{ description: string; amount: string }>;
    total_operating: string;
    total_investing: string;
    total_financing: string;
    beginning_cash: string;
    ending_cash: string;
    net_change: string;
    reconciles: boolean;
}

export function useGenerateTrialBalance() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (input: { as_of: string }) => {
            const res = await api.post<TrialBalance>('/reports/trial-balance', input);
            return res.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['reports'] }),
    });
}

export function useGenerateBalanceSheet() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (input: { as_of: string }) => {
            const res = await api.post<BalanceSheet>('/reports/balance-sheet', input);
            return res.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['reports'] }),
    });
}

export function useGenerateIncomeStatement() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (input: { from: string; to: string }) => {
            const res = await api.post<IncomeStatement>('/reports/income-statement', input);
            return res.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['reports'] }),
    });
}

export function useGenerateCashFlow() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (input: { from: string; to: string }) => {
            const res = await api.post<CashFlowStatement>('/reports/cash-flow', input);
            return res.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['reports'] }),
    });
}

export interface EquityStatement {
    id: string;
    pdf_path: string | null;
    period: string;
    total_beginning: string;
    total_movement: string;
    total_ending: string;
    net_income_for_period: string;
    total_equity: string;
    account_count: number;
}

export function useGenerateEquityStatement() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (input: { from: string; to: string }) => {
            const res = await api.post<EquityStatement>('/reports/equity-statement', input);
            return res.data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['reports'] }),
    });
}

export interface JournalBookResult {
    id: string;
    pdf_path: string | null;
    entry_count: number;
    total_debit: string;
    total_credit: string;
    is_balanced: boolean;
}

export interface LedgerBookResult {
    id: string;
    pdf_path: string | null;
    entry_count: number;
    total_debit: string;
    total_credit: string;
    is_balanced: boolean;
}

export interface SubsidiaryBookResult {
    id: string;
    pdf_path: string | null;
    row_count: number;
    totals: Record<string, string> | null;
}

function makeBookHook<T>(endpoint: string) {
    return () => {
        const qc = useQueryClient();
        return useMutation({
            mutationFn: async (input: { from: string; to: string }) => {
                const res = await api.post<T>(endpoint, input);
                return res.data;
            },
            onSuccess: () => qc.invalidateQueries({ queryKey: ['reports'] }),
        });
    };
}

export const useGenerateGeneralJournal      = makeBookHook<JournalBookResult>('/reports/books/general-journal');
export const useGenerateGeneralLedger       = makeBookHook<LedgerBookResult>('/reports/books/general-ledger');
export const useGenerateSalesBook           = makeBookHook<SubsidiaryBookResult>('/reports/books/sales-book');
export const useGeneratePurchasesBook       = makeBookHook<SubsidiaryBookResult>('/reports/books/purchases-book');
export const useGenerateCashReceiptsBook    = makeBookHook<SubsidiaryBookResult>('/reports/books/cash-receipts-book');
export const useGenerateCashDisbursementsBook = makeBookHook<SubsidiaryBookResult>('/reports/books/cash-disbursements-book');
