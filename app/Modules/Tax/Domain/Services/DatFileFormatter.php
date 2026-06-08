<?php

declare(strict_types=1);

namespace App\Modules\Tax\Domain\Services;

use App\Modules\Tax\Domain\Entities\AlphalistEntry;

/**
 * BIR DAT file formatter — produces the fixed-format text files that
 * eBIRForms requires as attachments to 1601-EQ (SAWT), 1604-CF, and 1604-E.
 *
 * Format spec (BIR-published):
 *   - Pipe-delimited, ASCII, CR-LF line endings
 *   - One header (H...) row, many detail (D...) rows
 *   - TIN: 12 digits no formatting
 *   - Names: max 100 chars, comma-separated last,first if individual
 *   - Amounts: 2 decimal places, no thousand separators, decimal point
 *
 * This is the most common format mismatch source — a single off-by-one in
 * the field order makes BIR's validator reject the entire file. Test
 * thoroughly against BIR's published samples.
 */
final readonly class DatFileFormatter
{
    /**
     * @param  list<AlphalistEntry>  $entries
     * @param  array{tin: string, registered_name: string, period_from: string, period_to: string}  $header
     */
    public function formatSawt(array $entries, array $header): string
    {
        $lines = [];

        // Header row (H — header)
        $lines[] = implode('|', [
            'H',
            $this->normalizeTin($header['tin']),
            $this->truncate($header['registered_name'], 100),
            $header['period_from'],
            $header['period_to'],
            count($entries),
        ]);

        // Detail rows (D — detail)
        foreach ($entries as $entry) {
            $lines[] = implode('|', [
                'D',
                $this->normalizeTin($entry->tin),
                $this->truncate($entry->registeredName, 100),
                $entry->atcCode ?? '',
                $entry->paymentDate?->format('Y-m-d') ?? '',
                $this->formatAmount($entry->incomePayment),
                $this->formatAmount($entry->taxWithheld),
            ]);
        }

        return implode("\r\n", $lines)."\r\n";
    }

    /**
     * @param  list<AlphalistEntry>  $entries
     * @param  array{tin: string, registered_name: string, year: int}  $header
     */
    public function formatAnnualAlphalist(array $entries, array $header): string
    {
        $lines = [];

        $lines[] = implode('|', [
            'H',
            $this->normalizeTin($header['tin']),
            $this->truncate($header['registered_name'], 100),
            (string) $header['year'],
            count($entries),
        ]);

        foreach ($entries as $entry) {
            $lines[] = implode('|', [
                'D',
                $entry->schedule,
                $this->normalizeTin($entry->tin),
                $this->truncate($entry->registeredName, 100),
                $entry->atcCode ?? '',
                $entry->taxType ?? '',
                $this->formatAmount($entry->incomePayment),
                $this->formatAmount($entry->taxWithheld),
            ]);
        }

        return implode("\r\n", $lines)."\r\n";
    }

    private function normalizeTin(string $tin): string
    {
        // BIR DAT requires unformatted 12-digit TIN
        return preg_replace('/[^0-9]/', '', $tin) ?? '';
    }

    private function truncate(string $value, int $max): string
    {
        return mb_strimwidth($value, 0, $max, '');
    }

    private function formatAmount(string $amount): string
    {
        return bcadd($amount, '0', 2);
    }
}
