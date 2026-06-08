<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\Actions;

use App\Modules\Tax\Application\DTOs\Form2307BulkImportReport;
use App\Modules\Tax\Application\DTOs\Form2307BulkImportRow;
use App\Modules\Tax\Application\DTOs\Form2307ReceivedInput;
use App\Modules\Tax\Application\Exceptions\DuplicateForm2307ReceivedException;
use App\Modules\Tax\Domain\Services\Form2307CsvParser;
use DateTimeImmutable;
use Throwable;

/**
 * Imports many 2307s in one call. Designed for the operator who pastes the
 * customer statement at quarter-end — 50–500 rows is the realistic upper
 * bound; partial failure is expected and reported per-row.
 *
 * Each row is processed in isolation; one bad row doesn't roll the rest back.
 * That's deliberate: the operator needs to see "rows 7, 12, 31 failed because
 * X" so they can fix and re-upload only those, rather than restart the batch.
 *
 * Existing duplicates are reported as 'duplicate', NOT 'failed' — operators
 * commonly re-upload the full CSV after fixing 3 rows; we want successful
 * re-imports to highlight what changed, not flag every prior-existing cert.
 */
final readonly class BulkImportForm2307Received
{
    public function __construct(
        private Form2307CsvParser $parser,
        private ImportForm2307Received $importOne,
    ) {
    }

    public function executeFromCsv(
        string $csvContent,
        string $companyId,
        string $recordedBy,
    ): Form2307BulkImportReport {
        $rows = $this->parser->parse($csvContent);
        return $this->execute($rows, $companyId, $recordedBy);
    }

    /**
     * @param  list<Form2307BulkImportRow>  $rows
     */
    public function execute(
        array $rows,
        string $companyId,
        string $recordedBy,
    ): Form2307BulkImportReport {
        $successful = [];
        $duplicates = [];
        $failed     = [];

        foreach ($rows as $row) {
            try {
                $input = $this->toInput($row, $companyId, $recordedBy);
                $cert  = $this->importOne->execute($input);

                $successful[] = [
                    'row_no'        => $row->rowNo,
                    'cert_id'       => $cert->id->value,
                    'payor_tin'     => $cert->payorTin,
                    'tax_withheld'  => $cert->taxWithheld,
                ];
            } catch (DuplicateForm2307ReceivedException $e) {
                $duplicates[] = [
                    'row_no'     => $row->rowNo,
                    'error_type' => 'duplicate',
                    'message'    => $e->getMessage(),
                ];
            } catch (Throwable $e) {
                $failed[] = [
                    'row_no'     => $row->rowNo,
                    'error_type' => $this->classify($e),
                    'message'    => $e->getMessage(),
                ];
            }
        }

        return new Form2307BulkImportReport(
            totalRows:  count($rows),
            successful: $successful,
            duplicates: $duplicates,
            failed:     $failed,
        );
    }

    private function toInput(
        Form2307BulkImportRow $row,
        string $companyId,
        string $recordedBy,
    ): Form2307ReceivedInput {
        // Validate required fields are present (parser left blanks as null)
        $required = ['payorTin', 'payorRegisteredName', 'atcCode', 'periodFrom', 'periodTo', 'incomePayment', 'taxWithheld'];
        foreach ($required as $field) {
            if ($row->{$field} === null) {
                throw new \InvalidArgumentException("Missing required field: {$field}.");
            }
        }

        return new Form2307ReceivedInput(
            companyId:           $companyId,
            customerId:          null,
            payorTin:            $row->payorTin,
            payorRegisteredName: $row->payorRegisteredName,
            payorBranchCode:     $row->payorBranchCode ?? '000',
            payorAddress:        $row->payorAddress,
            certificateNo:       $row->certificateNo,
            atcCode:             $row->atcCode,
            periodFrom:          $this->parseDate($row->periodFrom, 'period_from'),
            periodTo:            $this->parseDate($row->periodTo, 'period_to'),
            incomePayment:       $this->normalizeAmount($row->incomePayment),
            taxWithheld:         $this->normalizeAmount($row->taxWithheld),
            sourcePdfPath:       null,
            entryMethod:         'csv_import',
            journalEntryId:      null,
            recordedBy:          $recordedBy,
        );
    }

    private function parseDate(string $raw, string $fieldName): DateTimeImmutable
    {
        // Accept YYYY-MM-DD (ISO) and MM/DD/YYYY (Excel default); reject everything else.
        $iso = DateTimeImmutable::createFromFormat('Y-m-d', $raw);
        if ($iso !== false && $iso->format('Y-m-d') === $raw) {
            return $iso;
        }
        $us = DateTimeImmutable::createFromFormat('m/d/Y', $raw);
        if ($us !== false) {
            return $us;
        }
        throw new \InvalidArgumentException(
            "Invalid date '{$raw}' for {$fieldName}; use YYYY-MM-DD or MM/DD/YYYY."
        );
    }

    /**
     * Strip thousands separators ("1,234.56" → "1234.56"). Reject anything
     * with non-numeric remnants after that.
     */
    private function normalizeAmount(string $raw): string
    {
        $clean = str_replace([',', ' ', '₱'], '', $raw);
        if (! preg_match('/^-?\d+(\.\d{1,4})?$/', $clean)) {
            throw new \InvalidArgumentException("Invalid amount '{$raw}'.");
        }
        return $clean;
    }

    private function classify(Throwable $e): string
    {
        $class = (new \ReflectionClass($e))->getShortName();
        return match (true) {
            str_contains($class, 'InvalidArgument') => 'validation',
            str_contains($class, 'Tin')             => 'invalid_tin',
            default                                 => 'domain',
        };
    }
}
