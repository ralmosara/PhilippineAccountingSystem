<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Sales\Application\Contracts\CustomerRepositoryContract;
use App\Modules\Sales\Domain\Entities\Customer;
use App\Modules\Sales\Domain\ValueObjects\CustomerId;
use Illuminate\Support\Facades\DB;

final readonly class CreateCustomer
{
    public function __construct(
        private CustomerRepositoryContract $customers,
        private AuditWriterContract $audit,
    ) {
    }

    /**
     * @param  array{
     *     registered_name: string,
     *     tin?: string|null,
     *     is_vat_registered?: bool,
     *     is_government?: bool,
     *     is_senior_citizen?: bool,
     *     is_pwd?: bool,
     *     email?: string|null,
     *     payment_terms_days?: int,
     * }  $data
     */
    public function execute(string $companyId, array $data, string $actorId): Customer
    {
        return DB::transaction(function () use ($companyId, $data, $actorId) {
            $customer = new Customer(
                id:               CustomerId::generate(),
                companyId:        $companyId,
                customerNo:       $this->customers->nextCustomerNo($companyId),
                registeredName:   $data['registered_name'],
                tin:              $data['tin'] ?? null,
                isVatRegistered:  (bool) ($data['is_vat_registered'] ?? true),
                isGovernment:     (bool) ($data['is_government'] ?? false),
                isSeniorCitizen:  (bool) ($data['is_senior_citizen'] ?? false),
                isPwd:            (bool) ($data['is_pwd'] ?? false),
                email:            $data['email'] ?? null,
                paymentTermsDays: (int) ($data['payment_terms_days'] ?? 0),
            );

            $this->customers->save($customer);

            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $companyId,
                eventType:   'customer.created',
                aggregate:   'Customer',
                aggregateId: $customer->id->value,
                payload: [
                    'customer_no'    => $customer->customerNo,
                    'name'           => $customer->registeredName,
                    'tin'            => $customer->tin,
                    'is_government'  => $customer->isGovernment,
                ],
            );

            return $customer;
        });
    }
}
