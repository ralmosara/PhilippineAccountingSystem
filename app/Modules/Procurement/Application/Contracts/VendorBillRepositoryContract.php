<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Application\Contracts;

use App\Modules\Procurement\Domain\Entities\VendorBill;
use App\Modules\Procurement\Domain\ValueObjects\VendorBillId;

interface VendorBillRepositoryContract
{
    public function findById(VendorBillId $id): ?VendorBill;

    public function save(VendorBill $bill): void;
}
