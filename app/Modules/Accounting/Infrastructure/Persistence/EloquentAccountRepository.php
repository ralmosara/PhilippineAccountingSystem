<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Persistence;

use App\Modules\Accounting\Application\Contracts\AccountRepositoryContract;
use App\Modules\Accounting\Domain\Entities\Account;
use App\Modules\Accounting\Domain\ValueObjects\AccountCode;
use App\Modules\Accounting\Domain\ValueObjects\AccountId;
use App\Modules\Accounting\Infrastructure\Persistence\Eloquent\AccountModel;
use Illuminate\Support\Facades\Cache;

final class EloquentAccountRepository implements AccountRepositoryContract
{
    public function findById(AccountId $id): ?Account
    {
        $model = AccountModel::query()->find($id->value);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByCode(string $companyId, AccountCode $code): ?Account
    {
        $model = AccountModel::query()
            ->where('company_id', $companyId)
            ->where('code', $code->value)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function isPostable(AccountId $id): bool
    {
        // Cached for 5 minutes — postable status rarely changes
        return (bool) Cache::remember(
            key: "accounting.accounts.postable.{$id->value}",
            ttl: 300,
            callback: fn () => AccountModel::query()
                ->where('id', $id->value)
                ->where('is_active', true)
                ->where('is_postable', true)
                ->exists(),
        );
    }

    private function toDomain(AccountModel $model): Account
    {
        return new Account(
            id:            new AccountId($model->id),
            companyId:     $model->company_id,
            code:          new AccountCode($model->code),
            name:          $model->name,
            type:          $model->type,
            normalBalance: $model->normal_balance,
            parentId:      $model->parent_id ? new AccountId($model->parent_id) : null,
            path:          (string) $model->path,
            isPostable:    (bool) $model->is_postable,
            isActive:      (bool) $model->is_active,
        );
    }
}
