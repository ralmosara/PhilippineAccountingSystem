import { describe, expect, it } from 'vitest';

import { equalsScaled, fromScaled, sumScaled, toScaled } from './bcmath';

/**
 * BCMath compatibility — every monetary operation in the browser routes
 * through these utilities. If a centavo slips here it shows up on every
 * journal entry, every invoice, every tax return.
 */
describe('toScaled', () => {
    it('scales a simple decimal string to BigInt at 4dp', () => {
        // "1234.56" → 12345600n (4 decimal places of scale)
        expect(toScaled('1234.56')).toBe(12345600n);
    });

    it('scales an integer with no decimal point', () => {
        expect(toScaled('100')).toBe(1000000n);
    });

    it('pads short fractions to the 4-decimal scale', () => {
        // "1.5" → 1.5000 → 15000n
        expect(toScaled('1.5')).toBe(15000n);
    });

    it('truncates fractions longer than 4dp (matches Money VO behaviour)', () => {
        // "0.12345" → 1234n (the trailing "5" is dropped, not rounded)
        expect(toScaled('0.12345')).toBe(1234n);
    });

    it('handles negative values', () => {
        expect(toScaled('-100.5')).toBe(-1005000n);
    });

    it('strips thousands separators', () => {
        expect(toScaled('1,234.56')).toBe(12345600n);
    });

    it('returns 0n for null, undefined, empty string, and lone punctuation', () => {
        expect(toScaled(null)).toBe(0n);
        expect(toScaled(undefined)).toBe(0n);
        expect(toScaled('')).toBe(0n);
        expect(toScaled('-')).toBe(0n);
        expect(toScaled('.')).toBe(0n);
    });

    it('returns 0n for non-numeric garbage (does not throw)', () => {
        expect(toScaled('abc')).toBe(0n);
        expect(toScaled('1.2.3')).toBe(0n);
    });

    it('accepts a number argument', () => {
        expect(toScaled(1234.56)).toBe(12345600n);
        expect(toScaled(0)).toBe(0n);
    });
});

describe('fromScaled', () => {
    it('renders BigInt back to 2dp by default', () => {
        expect(fromScaled(12345600n)).toBe('1234.56');
    });

    it('renders to 4dp when requested', () => {
        expect(fromScaled(12345678n, 4)).toBe('1234.5678');
    });

    it('preserves sign on negatives', () => {
        expect(fromScaled(-1005000n)).toBe('-100.50');
    });

    it('renders sub-centavo values that get truncated at 2dp', () => {
        // 0.0099 scaled = 99n, rendered to 2dp = "0.00" (NOT rounded up)
        expect(fromScaled(99n)).toBe('0.00');
    });

    it('renders zero without sign', () => {
        expect(fromScaled(0n)).toBe('0.00');
    });

    it('round-trips toScaled ↔ fromScaled losslessly at 4dp', () => {
        const cases = ['0.0001', '1.2345', '100', '99999.9999', '-1.2345'];
        for (const c of cases) {
            expect(fromScaled(toScaled(c), 4)).toBe(c.includes('.') ? c : `${c}.0000`);
        }
    });
});

describe('sumScaled', () => {
    it('sums a list of numeric strings exactly', () => {
        // 0.1 + 0.2 in JS Numbers would be 0.30000000000000004; BigInt is exact
        expect(fromScaled(sumScaled(['0.1', '0.2']))).toBe('0.30');
    });

    it('survives the classic 200-row centavo-drift scenario', () => {
        // 200 rows of 0.01 = 2.00 exactly; JS .reduce((a, b) => a + b, 0) drifts
        const rows = new Array(200).fill('0.01');
        expect(fromScaled(sumScaled(rows))).toBe('2.00');
    });

    it('accepts bigints already in scaled form (no re-conversion)', () => {
        // Caller may have done intermediate scaling math; pass straight through
        expect(sumScaled([toScaled('1.00'), toScaled('2.00'), 30000n])).toBe(60000n);
    });

    it('accepts mixed string + number + bigint + null + undefined inputs', () => {
        expect(sumScaled(['1.00', 2, null, undefined, 30000n])).toBe(60000n);
    });

    it('returns 0n for an empty array', () => {
        expect(sumScaled([])).toBe(0n);
    });
});

describe('equalsScaled', () => {
    it('compares two BigInts exactly', () => {
        expect(equalsScaled(toScaled('1.23'), toScaled('1.23'))).toBe(true);
        expect(equalsScaled(toScaled('1.23'), toScaled('1.24'))).toBe(false);
    });

    it('treats different precision inputs as equal when scaled', () => {
        // "1" and "1.0000" should both scale to 10000n
        expect(equalsScaled(toScaled('1'), toScaled('1.0000'))).toBe(true);
    });
});

/**
 * Domain regression suite: pin specific BIR/PFRS scenarios that bit us in
 * earlier batches. Add to this list any time a centavo bug ships in production.
 */
describe('BIR-grade regression scenarios', () => {
    it('VAT 12% on ₱10,000 = exactly ₱1,200.00 (no float drift)', () => {
        const vatable = toScaled('10000');
        const vat = (vatable * 12n) / 100n;
        expect(fromScaled(vat)).toBe('1200.00');
    });

    it('Senior-citizen 20% discount on ₱5,000 = ₱1,000.00', () => {
        const gross = toScaled('5000');
        const discount = (gross * 20n) / 100n;
        expect(fromScaled(discount)).toBe('1000.00');
    });

    it('JV with 50 split-50 lines balances to the centavo', () => {
        // Common pattern: 50 debit lines totalling 12,500.00 vs 1 credit line 12,500.00
        const debits = new Array(50).fill('250.00');
        const credits = ['12500.00'];
        expect(equalsScaled(sumScaled(debits), sumScaled(credits))).toBe(true);
    });

    it('Government 5% withheld VAT on ₱100,000 vatable = ₱5,000.00', () => {
        const vatable = toScaled('100000');
        const wht = (vatable * 5n) / 100n;
        expect(fromScaled(wht)).toBe('5000.00');
    });

    it('Moving average roll-forward: 80 units @ ₱8 + 50 @ ₱10 → unit cost ₱8.7692', () => {
        // Backend's MovingAverageCalculator output: (640 + 500) / 130 = 8.76923077
        // We just verify the scaled arithmetic primitives needed for the UI display.
        const totalValue = toScaled('640') + toScaled('500');
        const totalQty = toScaled('130');
        // 10000 = SCALE_FACTOR exposed indirectly via toScaled('1')
        const avg = (totalValue * toScaled('1')) / totalQty;
        // Result at 4dp: 8.7692 (BCMath truncate, not round)
        expect(fromScaled(avg, 4)).toBe('8.7692');
    });
});
