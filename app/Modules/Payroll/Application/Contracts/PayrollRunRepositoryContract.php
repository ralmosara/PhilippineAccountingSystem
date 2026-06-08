<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Contracts;

use App\Modules\Payroll\Domain\Entities\PayrollRun;
use App\Modules\Payroll\Domain\ValueObjects\PayrollRunId;

interface PayrollRunRepositoryContract
{
    public function findById(PayrollRunId $id): ?PayrollRun;

    public function save(PayrollRun $run): void;

    public function nextRunNo(string $companyId, string $runType, int $year, int $month): string;
}
