<?php

declare(strict_types=1);

namespace App\Modules\FixedAssets\Infrastructure\Providers;

use App\Modules\FixedAssets\Application\Contracts\FixedAssetRepositoryContract;
use App\Modules\FixedAssets\Infrastructure\Persistence\EloquentFixedAssetRepository;
use Illuminate\Support\ServiceProvider;

final class FixedAssetsServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        FixedAssetRepositoryContract::class => EloquentFixedAssetRepository::class,
    ];

    public function register(): void
    {
    }

    public function boot(): void
    {
    }
}
