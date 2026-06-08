/**
 * Browser-side decimal arithmetic for accounting amounts.
 *
 * We never use Number for money totals — JS floats lose centavos at scale.
 * For the journal-entry validator we need a "are sum(debits) === sum(credits)?"
 * answer that survives 200-line entries with mixed precision. The pattern:
 * scale every value to a fixed exponent (4 decimals — matches the Laravel
 * Money VO), sum in BigInt, compare BigInts.
 */

const SCALE = 4;                       // matches App\Modules\Accounting\Domain\ValueObjects\Money
const SCALE_FACTOR = 10n ** BigInt(SCALE);

/** Parses a numeric string like "1234.56" to its scaled BigInt ("12345600"). */
export function toScaled(value: string | number | null | undefined): bigint {
    if (value === null || value === undefined || value === '') return 0n;
    const s = typeof value === 'number' ? value.toString() : value.trim();
    if (s === '' || s === '-' || s === '.') return 0n;

    const negative = s.startsWith('-');
    const cleaned = (negative ? s.slice(1) : s).replace(/,/g, '');
    if (!/^\d*(\.\d*)?$/.test(cleaned)) return 0n;

    const [whole = '0', frac = ''] = cleaned.split('.');
    const fracPadded = (frac + '0'.repeat(SCALE)).slice(0, SCALE);
    const combined  = (whole === '' ? '0' : whole) + fracPadded;
    const big = BigInt(combined || '0');
    return negative ? -big : big;
}

/** Inverse — render a scaled BigInt back to a 2dp display string ("12345600" → "1234.56"). */
export function fromScaled(value: bigint, dp: 2 | 4 = 2): string {
    const negative = value < 0n;
    const abs = negative ? -value : value;
    const whole = abs / SCALE_FACTOR;
    const frac  = abs % SCALE_FACTOR;
    const fracStr = frac.toString().padStart(SCALE, '0').slice(0, dp);
    const out = `${whole}.${fracStr}`;
    return negative ? `-${out}` : out;
}

/**
 * Sums an array of numeric strings exactly. Returns the BigInt-scaled total
 * so the caller can compare two sums or render either side. Accepts bigints
 * already in scaled form (callers that did intermediate math don't need to
 * convert back to string just to sum).
 */
export function sumScaled(values: Array<string | number | bigint | null | undefined>): bigint {
    let total = 0n;
    for (const v of values) {
        total += typeof v === 'bigint' ? v : toScaled(v);
    }
    return total;
}

export function equalsScaled(a: bigint, b: bigint): boolean {
    return a === b;
}
