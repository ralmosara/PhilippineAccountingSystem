<?php

declare(strict_types=1);

namespace App\Modules\Tax\Domain\Services;

use App\Modules\Tax\Application\DTOs\Form2307BulkImportRow;
use InvalidArgumentException;

/**
 * Parses an operator-supplied CSV into a list of Form2307BulkImportRow DTOs.
 *
 * Expected header (case-insensitive, exact column count enforced):
 *
 *   payor_tin, payor_registered_name, payor_branch_code, payor_address,
 *   certificate_no, atc_code, period_from, period_to,
 *   income_payment, tax_withheld
 *
 * The parser only fails on structural problems (wrong header, malformed
 * CSV). Row-level validation (TIN format, amount sanity, etc.) is the
 * downstream Action / Validator's job — the parser hands every row over
 * to the importer regardless, so the operator sees a row-by-row report
 * rather than a wall of errors from the first bad cell.
 */
final class Form2307CsvParser
{
    /** @var list<string> */
    public const HEADERS = [
        'payor_tin', 'payor_registered_name', 'payor_branch_code', 'payor_address',
        'certificate_no', 'atc_code', 'period_from', 'period_to',
        'income_payment', 'tax_withheld',
    ];

    /**
     * @return list<Form2307BulkImportRow>
     */
    public function parse(string $csvContent): array
    {
        // Strip UTF-8 BOM if present — Excel exports include it by default.
        if (str_starts_with($csvContent, "\xEF\xBB\xBF")) {
            $csvContent = substr($csvContent, 3);
        }

        $lines = preg_split('/\r\n|\n|\r/', $csvContent) ?: [];
        // Drop trailing blank line(s)
        while ($lines !== [] && trim(end($lines)) === '') {
            array_pop($lines);
        }
        if ($lines === []) {
            throw new InvalidArgumentException('CSV is empty.');
        }

        $header = $this->splitLine(array_shift($lines));
        $this->assertHeader($header);

        $rows = [];
        $rowNo = 0;
        foreach ($lines as $line) {
            $rowNo++;
            if (trim($line) === '') continue;          // skip blank rows silently
            $fields = $this->splitLine($line);
            // Pad missing trailing fields to expected width
            $fields = array_pad($fields, count(self::HEADERS), '');

            $rows[] = new Form2307BulkImportRow(
                rowNo:               $rowNo,
                payorTin:            $this->nullIfBlank($fields[0]),
                payorRegisteredName: $this->nullIfBlank($fields[1]),
                payorBranchCode:     $this->nullIfBlank($fields[2]) ?? '000',
                payorAddress:        $this->nullIfBlank($fields[3]),
                certificateNo:       $this->nullIfBlank($fields[4]),
                atcCode:             $this->nullIfBlank($fields[5]),
                periodFrom:          $this->nullIfBlank($fields[6]),
                periodTo:            $this->nullIfBlank($fields[7]),
                incomePayment:       $this->nullIfBlank($fields[8]),
                taxWithheld:         $this->nullIfBlank($fields[9]),
            );
        }

        return $rows;
    }

    /** @param list<string> $header */
    private function assertHeader(array $header): void
    {
        $normalized = array_map(
            static fn (string $h) => strtolower(trim($h)),
            $header,
        );
        if ($normalized !== self::HEADERS) {
            throw new InvalidArgumentException(
                "CSV header mismatch. Expected exactly:\n  "
                .implode(',', self::HEADERS)
                ."\nGot:\n  ".implode(',', $normalized),
            );
        }
    }

    /** Splits a CSV line; uses PHP's built-in str_getcsv for proper quote handling. */
    private function splitLine(string $line): array
    {
        return str_getcsv($line, escape: '\\');
    }

    private function nullIfBlank(string $v): ?string
    {
        $t = trim($v);
        return $t === '' ? null : $t;
    }
}
