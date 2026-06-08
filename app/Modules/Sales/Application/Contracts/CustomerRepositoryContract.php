<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application\Contracts;

use App\Modules\Sales\Domain\Entities\Customer;
use App\Modules\Sales\Domain\ValueObjects\CustomerId;

interface CustomerRepositoryContract
{
    public function findById(CustomerId $id): ?Customer;

    public function save(Customer $customer): void;

    public function nextCustomerNo(string $companyId): string;
}
