<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Contracts;

interface ReportPdfRendererContract
{
    /**
     * Renders a report payload to PDF; returns the MinIO key.
     *
     * @param  array<string, mixed>  $payload  the report's serialized lines + totals
     */
    public function render(string $reportType, string $companyId, array $payload): string;
}
