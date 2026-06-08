<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application\Contracts;

use App\Modules\Sales\Domain\Entities\SalesInvoice;
use App\Modules\Sales\Domain\ValueObjects\SalesInvoiceId;

interface SalesInvoiceRepositoryContract
{
    public function findById(SalesInvoiceId $id): ?SalesInvoice;

    public function save(SalesInvoice $invoice): void;

    /** Allocates an SI sequence number from accounting.document_series. */
    public function allocateDocNo(string $documentSeriesId): string;
}
