<?php

declare(strict_types=1);

namespace App\Modules\Manufacturing\Application\Contracts;

use App\Modules\Manufacturing\Domain\Entities\WorkOrder;
use App\Modules\Manufacturing\Domain\ValueObjects\WorkOrderId;

interface WorkOrderRepositoryContract
{
    public function findById(WorkOrderId $id): ?WorkOrder;

    public function save(WorkOrder $workOrder): void;

    /**
     * @return list<WorkOrder>
     */
    public function listForCompany(string $companyId, int $perPage = 25, int $page = 1): array;

    /**
     * Allocates the next sequential work order number for the company.
     * Format: WO-{YYYY}-{NNN} e.g. WO-2026-001
     * MUST be called inside a transaction to avoid gaps.
     */
    public function nextWorkOrderNo(string $companyId): string;
}
