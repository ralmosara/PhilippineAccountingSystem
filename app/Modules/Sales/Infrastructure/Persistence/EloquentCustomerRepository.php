<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure\Persistence;

use App\Modules\Sales\Application\Contracts\CustomerRepositoryContract;
use App\Modules\Sales\Domain\Entities\Customer;
use App\Modules\Sales\Domain\ValueObjects\CustomerId;
use App\Modules\Sales\Infrastructure\Persistence\Eloquent\CustomerModel;

final class EloquentCustomerRepository implements CustomerRepositoryContract
{
    public function findById(CustomerId $id): ?Customer
    {
        $model = CustomerModel::query()->find($id->value);
        return $model ? $this->toDomain($model) : null;
    }

    public function save(Customer $customer): void
    {
        CustomerModel::query()->updateOrInsert(
            ['id' => $customer->id->value],
            [
                'company_id'         => $customer->companyId,
                'customer_no'        => $customer->customerNo,
                'registered_name'    => $customer->registeredName,
                'tin'                => $customer->tin,
                'is_vat_registered'  => $customer->isVatRegistered,
                'is_government'      => $customer->isGovernment,
                'is_senior_citizen'  => $customer->isSeniorCitizen,
                'is_pwd'             => $customer->isPwd,
                'email'              => $customer->email,
                'payment_terms_days' => $customer->paymentTermsDays,
                'is_active'          => $customer->isActive,
                'updated_at'         => now(),
                'created_at'         => now(),
            ],
        );
    }

    public function nextCustomerNo(string $companyId): string
    {
        $count = CustomerModel::query()->where('company_id', $companyId)->count();
        return 'CUST-'.str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);
    }

    private function toDomain(CustomerModel $m): Customer
    {
        return new Customer(
            id:               new CustomerId($m->id),
            companyId:        $m->company_id,
            customerNo:       $m->customer_no,
            registeredName:   $m->registered_name,
            tin:              $m->tin,
            isVatRegistered:  (bool) $m->is_vat_registered,
            isGovernment:     (bool) $m->is_government,
            isSeniorCitizen:  (bool) $m->is_senior_citizen,
            isPwd:            (bool) $m->is_pwd,
            email:            $m->email,
            paymentTermsDays: (int) $m->payment_terms_days,
            isActive:         (bool) $m->is_active,
        );
    }
}
