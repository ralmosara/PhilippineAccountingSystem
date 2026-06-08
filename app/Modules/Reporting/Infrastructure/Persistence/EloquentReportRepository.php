<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Infrastructure\Persistence;

use App\Modules\Reporting\Application\Contracts\ReportRepositoryContract;
use App\Modules\Reporting\Infrastructure\Persistence\Eloquent\ReportRunModel;

final class EloquentReportRepository implements ReportRepositoryContract
{
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
    ): void {
        ReportRunModel::query()->updateOrInsert(
            ['id' => $id],
            [
                'company_id'   => $companyId,
                'report_type'  => $reportType,
                'period_from'  => $periodFrom,
                'period_to'    => $periodTo,
                'as_of_date'   => $asOfDate,
                'payload'      => json_encode($payload, JSON_THROW_ON_ERROR),
                'pdf_path'     => $pdfPath,
                'csv_path'     => $csvPath,
                'generated_at' => now(),
                'generated_by' => $generatedBy,
                'updated_at'   => now(),
                'created_at'   => now(),
            ],
        );
    }
}
