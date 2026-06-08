<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence;

use App\Modules\Identity\Application\Contracts\UserRepositoryContract;
use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Identity\Domain\ValueObjects\Email;
use App\Modules\Identity\Domain\ValueObjects\UserId;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\UserModel;

final class EloquentUserRepository implements UserRepositoryContract
{
    public function findByEmail(Email $email): ?User
    {
        $model = UserModel::query()->where('email', $email->value)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findById(UserId $id): ?User
    {
        $model = UserModel::query()->find($id->value);

        return $model ? $this->toDomain($model) : null;
    }

    public function recordLastLogin(UserId $id, string $ipAddress): void
    {
        UserModel::query()->where('id', $id->value)->update([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
        ]);
    }

    private function toDomain(UserModel $model): User
    {
        return new User(
            id:         new UserId($model->id),
            companyId:  $model->company_id,
            email:      new Email($model->email),
            fullName:   $model->full_name,
            mfaEnabled: (bool) $model->mfa_enabled,
            isActive:   (bool) $model->is_active,
        );
    }
}
