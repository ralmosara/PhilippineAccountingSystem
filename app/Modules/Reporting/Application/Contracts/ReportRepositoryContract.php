<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Contracts;

interface ReportRepositoryContract
{
    /**
     * Persists a generated report run for audit / re-download.
     *
     * @param  array<string, mixed>  $payload  rendered statement (lines + totals)
     */
    public function save(
        string $id,
        string $companyId,
        string $reportType,
        string $periodFrom,
        string $periodTo,
        ?string $asOfDate,
        array $payload,
        ?string $pdfPath,
        ?string $csvPath,
        string $generatedBy,
    ): void;
}
