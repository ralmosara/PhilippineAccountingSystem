<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application\Contracts;

use App\Modules\Sales\Domain\Entities\OfficialReceipt;
use App\Modules\Sales\Domain\ValueObjects\OfficialReceiptId;

interface OfficialReceiptRepositoryContract
{
    public function findById(OfficialReceiptId $id): ?OfficialReceipt;

    public function save(OfficialReceipt $receipt): void;

    public function allocateDocNo(string $documentSeriesId): string;
}
