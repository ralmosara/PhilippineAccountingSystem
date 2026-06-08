/**
 * Money formatting helpers — accounting-grade.
 * All monetary values are formatted with tabular numerals + 2 decimals + grouping.
 */

const PHP = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const PLAIN = new Intl.NumberFormat('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

export function formatPhp(amount: number | string | null | undefined): string {
    if (amount === null || amount === undefined || amount === '') return '—';
    const n = typeof amount === 'string' ? Number(amount) : amount;
    if (!Number.isFinite(n)) return '—';
    return PHP.format(n);
}

export function formatNumber(amount: number | string | null | undefined): string {
    if (amount === null || amount === undefined || amount === '') return '—';
    const n = typeof amount === 'string' ? Number(amount) : amount;
    if (!Number.isFinite(n)) return '—';
    return PLAIN.format(n);
}

/**
 * For ledger views: empty string for zero (so debit OR credit column shows the value).
 */
export function formatLedger(amount: number | string | null | undefined): string {
    const n = typeof amount === 'string' ? Number(amount) : amount;
    if (!n || !Number.isFinite(n) || n === 0) return '';
    return PLAIN.format(n);
}
