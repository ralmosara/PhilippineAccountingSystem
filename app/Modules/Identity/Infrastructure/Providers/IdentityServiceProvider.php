<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Providers;

use App\Modules\Identity\Application\Contracts\AuthenticatorContract;
use App\Modules\Identity\Application\Contracts\UserRepositoryContract;
use App\Modules\Identity\Infrastructure\Authentication\SanctumAuthenticator;
use App\Modules\Identity\Infrastructure\Persistence\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;

final class IdentityServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        UserRepositoryContract::class => EloquentUserRepository::class,
        AuthenticatorContract::class  => SanctumAuthenticator::class,
    ];

    public function register(): void
    {
        // Bindings auto-resolved from $this->bindings
    }

    public function boot(): void
    {
        // Module-level boot logic. Register event listeners here.
    }
}
