<?php

declare(strict_types=1);

namespace App\Modules\Tax\Infrastructure\Persistence;

use App\Modules\Tax\Application\Contracts\OsdElectionRepositoryContract;
use App\Modules\Tax\Domain\Entities\OsdElection;
use App\Modules\Tax\Domain\ValueObjects\OsdElectionId;
use App\Modules\Tax\Infrastructure\Persistence\Eloquent\OsdElectionModel;
use DateTimeImmutable;

final class EloquentOsdElectionRepository implements OsdElectionRepositoryContract
{
    public function findById(OsdElectionId $id): ?OsdElection
    {
        $m = OsdElectionModel::query()->find($id->value);
        return $m ? $this->toDomain($m) : null;
    }

    public function findActive(
        string $companyId,
        int $fiscalYear,
        string $taxpayerType,
    ): ?OsdElection {
        $m = OsdElectionModel::query()
            ->where('company_id', $companyId)
            ->where('fiscal_year', $fiscalYear)
            ->where('taxpayer_type', $taxpayerType)
            ->whereNull('superseded_at')
            ->first();

        return $m ? $this->toDomain($m) : null;
    }

    public function save(OsdElection $election): void
    {
        OsdElectionModel::query()->updateOrInsert(
            ['id' => $election->id->value],
            [
                'company_id'              => $election->companyId,
                'fiscal_year'             => $election->fiscalYear,
                'taxpayer_type'           => $election->taxpayerType,
                'regime'                  => $election->regime,
                'declared_in_form_type'   => $election->declaredInFormType,
                'declared_in_quarter'     => $election->declaredInQuarter,
                'declared_in_bir_form_id' => $election->declaredInBirFormId,
                'locked_at'               => $election->lockedAt,
                'locked_by'               => $election->lockedBy,
                'replaces_id'             => $election->replacesId?->value,
                'superseded_at'           => $election->supersededAt,
                'supersede_reason'        => $election->supersedeReason,
                'updated_at'              => now(),
                'created_at'              => now(),
            ],
        );
    }

    private function toDomain(OsdElectionModel $m): OsdElection
    {
        $election = new OsdElection(
            id:                  new OsdElectionId((string) $m->id),
            companyId:           (string) $m->company_id,
            fiscalYear:          (int) $m->fiscal_year,
            taxpayerType:        (string) $m->taxpayer_type,
            regime:              (string) $m->regime,
            declaredInFormType:  (string) $m->declared_in_form_type,
            declaredInQuarter:   $m->declared_in_quarter !== null ? (int) $m->declared_in_quarter : null,
            declaredInBirFormId: $m->declared_in_bir_form_id !== null ? (string) $m->declared_in_bir_form_id : null,
            lockedAt:            new DateTimeImmutable($m->locked_at->toIso8601String()),
            lockedBy:            (string) $m->locked_by,
            replacesId:          $m->replaces_id !== null ? new OsdElectionId((string) $m->replaces_id) : null,
        );

        if ($m->superseded_at !== null) {
            $election->supersededAt    = new DateTimeImmutable($m->superseded_at->toIso8601String());
            $election->supersedeReason = $m->supersede_reason !== null
                ? (string) $m->supersede_reason
                : null;
        }

        return $election;
    }
}
