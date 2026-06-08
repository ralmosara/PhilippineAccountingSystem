<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\DTOs;

/**
 * Operator-facing report after a CSV bulk import.
 *
 *   - successful: rows that produced a saved Form2307Received
 *   - duplicates: rows that hit existing dedupe (skipped, not failed)
 *   - failed:     rows that threw validation or domain errors (with messages)
 *
 * @phpstan-type RowError array{row_no: int, error_type: string, message: string}
 */
final readonly class Form2307BulkImportReport
{
    /**
     * @param list<array{row_no: int, cert_id: string, payor_tin: string, tax_withheld: string}> $successful
     * @param list<RowError>  $duplicates
     * @param list<RowError>  $failed
     */
    public function __construct(
        public int $totalRows,
        public array $successful,
        public array $duplicates,
        public array $failed,
    ) {
    }

    public function successCount(): int    { return count($this->successful); }
    public function duplicateCount(): int  { return count($this->duplicates); }
    public function failedCount(): int     { return count($this->failed); }

    /** True when at least one row landed; useful for partial-success HTTP responses. */
    public function hasAnySuccess(): bool  { return $this->successful !== []; }
}
