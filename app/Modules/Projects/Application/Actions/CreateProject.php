<?php

declare(strict_types=1);

namespace App\Modules\Projects\Application\Actions;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Projects\Application\Contracts\ProjectRepositoryContract;
use App\Modules\Projects\Application\Exceptions\DuplicateProjectCodeException;
use App\Modules\Projects\Domain\Entities\Project;
use App\Modules\Projects\Domain\ValueObjects\ProjectId;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final readonly class CreateProject
{
    public function __construct(
        private ProjectRepositoryContract $projects,
        private AuditWriterContract $audit,
    ) {
    }

    /**
     * @param  array{
     *     code: string,
     *     name: string,
     *     billing_type: string,
     *     contract_value?: string|null,
     *     budget_hours?: string|null,
     *     customer_id?: string|null,
     *     wip_account_id?: string|null,
     *     revenue_account_id?: string|null,
     *     starts_on?: string|null,
     *     ends_on?: string|null,
     * } $data
     */
    public function execute(
        string $companyId,
        array $data,
        string $actorId,
    ): Project {
        return DB::transaction(function () use ($companyId, $data, $actorId) {
            if ($this->projects->findByCode($companyId, $data['code']) !== null) {
                throw new DuplicateProjectCodeException($data['code']);
            }

            $project = new Project(
                id:               ProjectId::generate(),
                companyId:        $companyId,
                code:             $data['code'],
                name:             $data['name'],
                billingType:      $data['billing_type'],
                contractValue:    isset($data['contract_value']) && $data['contract_value'] !== null
                                      ? Money::php((string) $data['contract_value'])
                                      : null,
                budgetHours:      $data['budget_hours'] ?? null,
                customerId:       $data['customer_id'] ?? null,
                wipAccountId:     $data['wip_account_id'] ?? null,
                revenueAccountId: $data['revenue_account_id'] ?? null,
                startsOn:         isset($data['starts_on']) && $data['starts_on'] !== null
                                      ? new DateTimeImmutable($data['starts_on'])
                                      : null,
                endsOn:           isset($data['ends_on']) && $data['ends_on'] !== null
                                      ? new DateTimeImmutable($data['ends_on'])
                                      : null,
            );

            $this->projects->save($project);

            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $companyId,
                eventType:   'project.created',
                aggregate:   'Project',
                aggregateId: $project->id->value,
                payload: [
                    'code'         => $project->code,
                    'name'         => $project->name,
                    'billing_type' => $project->billingType,
                    'status'       => $project->status,
                ],
            );

            return $project;
        });
    }
}
