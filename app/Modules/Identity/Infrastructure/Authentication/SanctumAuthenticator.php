<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Authentication;

use App\Modules\Identity\Application\Contracts\AuthenticatorContract;
use App\Modules\Identity\Application\Contracts\UserRepositoryContract;
use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Identity\Domain\ValueObjects\Email;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Support\Facades\Hash;

final readonly class SanctumAuthenticator implements AuthenticatorContract
{
    public function __construct(private UserRepositoryContract $users)
    {
    }

    public function attempt(Email $email, string $password): ?User
    {
        $model = UserModel::query()->where('email', $email->value)->first();

        if (! $model || ! $model->is_active) {
            return null;
        }

        if (! Hash::check($password, $model->password_hash)) {
            return null;
        }

        return $this->users->findById(new \App\Modules\Identity\Domain\ValueObjects\UserId($model->id));
    }

    public function issueToken(User $user, string $deviceName): array
    {
        $model = UserModel::findOrFail($user->id->value);

        $expiresAt = new \DateTimeImmutable('+8 hours');

        $token = $model->createToken(
            name: $deviceName,
            abilities: ['*'],
            expiresAt: $expiresAt,
        );

        return [
            'token'      => $token->plainTextToken,
            'expires_at' => $expiresAt,
        ];
    }

    public function revokeToken(string $tokenId): void
    {
        \Laravel\Sanctum\PersonalAccessToken::find($tokenId)?->delete();
    }
}
