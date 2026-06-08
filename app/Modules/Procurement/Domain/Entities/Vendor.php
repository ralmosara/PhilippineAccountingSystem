<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Domain\Entities;

use App\Modules\Procurement\Domain\ValueObjects\VendorId;

final readonly class Vendor
{
    public function __construct(
        public VendorId $id,
        public string $companyId,
        public string $vendorNo,
        public string $registeredName,
        public ?string $tin,
        public bool $isVatRegistered,
        public bool $isGovernmentSupplier,
        public bool $isTopWithholdingAgent,
        public ?string $defaultAtcCode,
        public ?string $defaultWithholdingRate,
        public int $paymentTermsDays = 30,
        public ?string $email = null,
        public bool $isActive = true,
    ) {
    }

    public function hasWithholdingProfile(): bool
    {
        return $this->defaultAtcCode !== null;
    }
}
