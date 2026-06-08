<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Procurement\Application\Contracts\VendorRepositoryContract;
use App\Modules\Procurement\Domain\Entities\Vendor;
use App\Modules\Procurement\Domain\ValueObjects\VendorId;
use Illuminate\Support\Facades\DB;

final readonly class CreateVendor
{
    public function __construct(
        private VendorRepositoryContract $vendors,
        private AuditWriterContract $audit,
    ) {
    }

    /**
     * @param  array{
     *     registered_name: string,
     *     tin?: string|null,
     *     is_vat_registered?: bool,
     *     is_government_supplier?: bool,
     *     is_top_withholding_agent?: bool,
     *     default_atc_code?: string|null,
     *     default_withholding_rate?: string|null,
     *     payment_terms_days?: int,
     *     email?: string|null,
     * }  $data
     */
    public function execute(string $companyId, array $data, string $actorId): Vendor
    {
        return DB::transaction(function () use ($companyId, $data, $actorId) {
            $vendor = new Vendor(
                id:                       VendorId::generate(),
                companyId:                $companyId,
                vendorNo:                 $this->vendors->nextVendorNo($companyId),
                registeredName:           $data['registered_name'],
                tin:                      $data['tin'] ?? null,
                isVatRegistered:          (bool) ($data['is_vat_registered'] ?? true),
                isGovernmentSupplier:     (bool) ($data['is_government_supplier'] ?? false),
                isTopWithholdingAgent:    (bool) ($data['is_top_withholding_agent'] ?? false),
                defaultAtcCode:           $data['default_atc_code'] ?? null,
                defaultWithholdingRate:   $data['default_withholding_rate'] ?? null,
                paymentTermsDays:         (int) ($data['payment_terms_days'] ?? 30),
                email:                    $data['email'] ?? null,
            );

            $this->vendors->save($vendor);

            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $companyId,
                eventType:   'vendor.created',
                aggregate:   'Vendor',
                aggregateId: $vendor->id->value,
                payload: [
                    'vendor_no'        => $vendor->vendorNo,
                    'name'             => $vendor->registeredName,
                    'tin'              => $vendor->tin,
                    'default_atc_code' => $vendor->defaultAtcCode,
                ],
            );

            return $vendor;
        });
    }
}
