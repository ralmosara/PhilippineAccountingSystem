<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain\Entities;

use App\Modules\Sales\Domain\ValueObjects\CustomerId;

final readonly class Customer
{
    public function __construct(
        public CustomerId $id,
        public string $companyId,
        public string $customerNo,
        public string $registeredName,
        public ?string $tin,
        public bool $isVatRegistered,
        public bool $isGovernment,           // 5% withheld VAT applies
        public bool $isSeniorCitizen,        // RA 9994: 20% disc + VAT exempt
        public bool $isPwd,                  // RA 10754: 20% disc + VAT exempt
        public ?string $email = null,
        public int $paymentTermsDays = 0,
        public bool $isActive = true,
    ) {
    }

    /** Senior or PWD qualifies for the 20% discount + VAT exemption. */
    public function qualifiesForSeniorPwdDiscount(): bool
    {
        return $this->isSeniorCitizen || $this->isPwd;
    }
}
