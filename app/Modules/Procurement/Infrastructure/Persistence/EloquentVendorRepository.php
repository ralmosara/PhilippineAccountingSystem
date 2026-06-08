<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Infrastructure\Persistence;

use App\Modules\Procurement\Application\Contracts\VendorRepositoryContract;
use App\Modules\Procurement\Domain\Entities\Vendor;
use App\Modules\Procurement\Domain\ValueObjects\VendorId;
use App\Modules\Procurement\Infrastructure\Persistence\Eloquent\VendorModel;

final class EloquentVendorRepository implements VendorRepositoryContract
{
    public function findById(VendorId $id): ?Vendor
    {
        $model = VendorModel::query()->find($id->value);
        return $model ? $this->toDomain($model) : null;
    }

    public function save(Vendor $vendor): void
    {
        VendorModel::query()->updateOrInsert(
            ['id' => $vendor->id->value],
            [
                'company_id'                => $vendor->companyId,
                'vendor_no'                 => $vendor->vendorNo,
                'registered_name'           => $vendor->registeredName,
                'tin'                       => $vendor->tin,
                'is_vat_registered'         => $vendor->isVatRegistered,
                'is_government_supplier'    => $vendor->isGovernmentSupplier,
                'is_top_withholding_agent'  => $vendor->isTopWithholdingAgent,
                'default_atc_code'          => $vendor->defaultAtcCode,
                'default_withholding_rate'  => $vendor->defaultWithholdingRate,
                'payment_terms_days'        => $vendor->paymentTermsDays,
                'email'                     => $vendor->email,
                'is_active'                 => $vendor->isActive,
                'updated_at'                => now(),
                'created_at'                => now(),
            ],
        );
    }

    public function nextVendorNo(string $companyId): string
    {
        $count = VendorModel::query()->where('company_id', $companyId)->count();
        return 'VEN-'.str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);
    }

    private function toDomain(VendorModel $m): Vendor
    {
        return new Vendor(
            id:                       new VendorId($m->id),
            companyId:                $m->company_id,
            vendorNo:                 $m->vendor_no,
            registeredName:           $m->registered_name,
            tin:                      $m->tin,
            isVatRegistered:          (bool) $m->is_vat_registered,
            isGovernmentSupplier:     (bool) $m->is_government_supplier,
            isTopWithholdingAgent:    (bool) $m->is_top_withholding_agent,
            defaultAtcCode:           $m->default_atc_code,
            defaultWithholdingRate:   $m->default_withholding_rate ? (string) $m->default_withholding_rate : null,
            paymentTermsDays:         (int) $m->payment_terms_days,
            email:                    $m->email,
            isActive:                 (bool) $m->is_active,
        );
    }
}
