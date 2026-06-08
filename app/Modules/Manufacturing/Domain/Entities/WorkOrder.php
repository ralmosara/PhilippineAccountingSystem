<?php

declare(strict_types=1);

namespace App\Modules\Manufacturing\Domain\Entities;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Manufacturing\Domain\ValueObjects\BomId;
use App\Modules\Manufacturing\Domain\ValueObjects\WorkOrderId;
use App\Modules\Manufacturing\Domain\ValueObjects\WorkOrderStatus;
use DateTimeImmutable;
use DomainException;

final class WorkOrder
{
    /** @var list<ProductionRunLine> */
    public array $lines = [];

    public function __construct(
        public readonly WorkOrderId     $id,
        public readonly string          $companyId,
        public readonly BomId           $bomId,
        public readonly string          $workOrderNo,
        public string                   $quantityToProduce,  // BCMath string
        public string                   $quantityProduced,   // BCMath string
        public WorkOrderStatus          $status,
        public ?string                  $scheduledStart,     // date string Y-m-d or null
        public ?string                  $scheduledEnd,
        public ?DateTimeImmutable       $actualStart,
        public ?DateTimeImmutable       $actualEnd,
        public ?string                  $warehouseId,
        public ?string                  $wipAccountId,
        public ?string                  $finishedGoodsAccountId,
        public ?string                  $rawMaterialsAccountId,
        public string                   $totalMaterialCost,   // BCMath string
        public string                   $totalLaborCost,
        public string                   $totalOverheadCost,
        public string                   $totalProductionCost,
        public ?string                  $journalEntryId,
    ) {
    }

    public function release(): void
    {
        if ($this->status !== WorkOrderStatus::Draft) {
            throw new DomainException("Work order {$this->workOrderNo} must be in draft to release; current: {$this->status->value}.");
        }
        $this->status = WorkOrderStatus::Released;
    }

    public function start(): void
    {
        if (! in_array($this->status, [WorkOrderStatus::Draft, WorkOrderStatus::Released], true)) {
            throw new DomainException("Work order {$this->workOrderNo} cannot be started from status: {$this->status->value}.");
        }
        $this->status      = WorkOrderStatus::InProgress;
        $this->actualStart = new DateTimeImmutable();
    }

    public function complete(string $actorId): void
    {
        if (! in_array($this->status, [WorkOrderStatus::InProgress, WorkOrderStatus::Released], true)) {
            throw new DomainException("Work order {$this->workOrderNo} must be in_progress or released to complete; current: {$this->status->value}.");
        }
        $this->status    = WorkOrderStatus::Completed;
        $this->actualEnd = new DateTimeImmutable();
    }

    public function cancel(): void
    {
        if (in_array($this->status, [WorkOrderStatus::Completed, WorkOrderStatus::Cancelled], true)) {
            throw new DomainException("Work order {$this->workOrderNo} cannot be cancelled from status: {$this->status->value}.");
        }
        $this->status = WorkOrderStatus::Cancelled;
    }

    /**
     * Sums production run lines into the four cost totals.
     * Labor and overhead are prorated from the BOM per the ratio of
     * quantity_produced / standard_batch_size — but those are already
     * baked in at CompleteProductionRun time; here we just sum the line totals.
     */
    public function computeTotals(string $laborCost, string $overheadCost): void
    {
        $materialTotal = '0.00';
        foreach ($this->lines as $line) {
            $materialTotal = bcadd($materialTotal, $line->totalCost->amount, 2);
        }

        $this->totalMaterialCost   = $materialTotal;
        $this->totalLaborCost      = bcadd($laborCost, '0', 2);
        $this->totalOverheadCost   = bcadd($overheadCost, '0', 2);
        $this->totalProductionCost = bcadd(
            bcadd($materialTotal, $this->totalLaborCost, 2),
            $this->totalOverheadCost,
            2
        );
    }
}
