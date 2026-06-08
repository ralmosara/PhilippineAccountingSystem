<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\Contracts;

interface SellerProfileProviderContract
{
    /**
     * Resolve the "seller profile" block of the EIS payload from the company
     * (and optionally the branch) that owns the invoice.
     *
     * @return array{
     *     tin: string,
     *     branch_code: string,
     *     registered_name: string,
     *     trade_name: ?string,
     *     address: string,
     *     vat_status: 'vat'|'non-vat',
     *     accreditation_no: ?string,
     * }
     */
    public function forCompany(string $companyId, ?string $branchId = null): array;
}
