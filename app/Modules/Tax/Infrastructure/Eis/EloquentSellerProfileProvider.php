<?php

declare(strict_types=1);

namespace App\Modules\Tax\Infrastructure\Eis;

use App\Modules\Tax\Application\Contracts\SellerProfileProviderContract;
use Illuminate\Database\ConnectionInterface;
use RuntimeException;

/**
 * Reads the seller block of the EIS payload directly from identity.companies
 * (+ identity.branches when a branch is supplied).
 *
 * We don't introduce an Identity contract for this because:
 *   - the data we need is BIR-mandated and frozen (TIN, registered name,
 *     branch_code, address, vat_status, accreditation_no),
 *   - this is a read-only projection across a schema boundary that the
 *     Tax module already crosses for filing,
 *   - the alternative — a public Identity\Application\Contracts\CompanyReader —
 *     adds a layer with one call site.
 *
 * If this expands beyond the BIR EIS use case, lift it into Identity.
 */
final readonly class EloquentSellerProfileProvider implements SellerProfileProviderContract
{
    public function __construct(private ConnectionInterface $db)
    {
    }

    public function forCompany(string $companyId, ?string $branchId = null): array
    {
        $company = $this->db->selectOne(
            "SELECT tin, registered_name, trade_name, address, vat_status, cas_ptu_number
               FROM identity.companies
              WHERE id = ?::uuid",
            [$companyId],
        );

        if (! $company) {
            throw new RuntimeException("Company {$companyId} not found for EIS seller profile.");
        }

        $branchCode = '000';            // BIR convention: head office = '000'
        if ($branchId !== null) {
            $branch = $this->db->selectOne(
                "SELECT bir_branch_code FROM identity.branches WHERE id = ?::uuid",
                [$branchId],
            );
            if ($branch && isset($branch->bir_branch_code) && $branch->bir_branch_code !== '') {
                $branchCode = (string) $branch->bir_branch_code;
            }
        }

        return [
            'tin'              => (string) $company->tin,
            'branch_code'      => $branchCode,
            'registered_name'  => (string) $company->registered_name,
            'trade_name'       => $company->trade_name !== null ? (string) $company->trade_name : null,
            'address'          => (string) $company->address,
            'vat_status'       => ((string) $company->vat_status) === 'vat' ? 'vat' : 'non-vat',
            'accreditation_no' => $company->cas_ptu_number !== null ? (string) $company->cas_ptu_number : null,
        ];
    }
}
