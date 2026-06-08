<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\DTOs;

use DateTimeImmutable;

/**
 * The shape the application layer accepts for a 2307 import — whether it
 * arrived from HTTP, a CSV bulk import, or a manual paste-it-in operator
 * action. Keeps the Action signature stable across entry methods.
 */
final readonly class Form2307ReceivedInput
{
    public function __construct(
        public string $companyId,
        public ?string $customerId,
        public string $payorTin,
        public string $payorRegisteredName,
        public string $payorBranchCode,
        public ?string $payorAddress,
        public ?string $certificateNo,
        public string $atcCode,
        public DateTimeImmutable $periodFrom,
        public DateTimeImmutable $periodTo,
        public string $incomePayment,          // numeric string
        public string $taxWithheld,            // numeric string
        public ?string $sourcePdfPath,
        public string $entryMethod,            // 'manual' | 'pdf_upload' | 'csv_import'
        public ?string $journalEntryId,
        public string $recordedBy,
    ) {
    }
}
