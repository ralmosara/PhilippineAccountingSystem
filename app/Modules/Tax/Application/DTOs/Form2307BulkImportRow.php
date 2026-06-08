<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\DTOs;

/**
 * A single CSV row after parsing, before the per-row Action runs. Carries
 * row_no for error reporting back to the operator.
 */
final readonly class Form2307BulkImportRow
{
    public function __construct(
        public int $rowNo,                          // 1-based, excluding header
        public ?string $payorTin,
        public ?string $payorRegisteredName,
        public ?string $payorBranchCode,
        public ?string $payorAddress,
        public ?string $certificateNo,
        public ?string $atcCode,
        public ?string $periodFrom,                 // raw string from CSV
        public ?string $periodTo,
        public ?string $incomePayment,
        public ?string $taxWithheld,
    ) {
    }
}
