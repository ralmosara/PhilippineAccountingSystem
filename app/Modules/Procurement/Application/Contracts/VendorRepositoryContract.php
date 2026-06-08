<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Application\Contracts;

use App\Modules\Procurement\Domain\Entities\Vendor;
use App\Modules\Procurement\Domain\ValueObjects\VendorId;

interface VendorRepositoryContract
{
    public function findById(VendorId $id): ?Vendor;

    public function save(Vendor $vendor): void;

    public function nextVendorNo(string $companyId): string;
}
