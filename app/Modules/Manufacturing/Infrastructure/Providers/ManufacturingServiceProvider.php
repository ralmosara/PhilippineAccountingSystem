<?php

declare(strict_types=1);

namespace App\Modules\Manufacturing\Infrastructure\Providers;

use App\Modules\Manufacturing\Application\Contracts\BomRepositoryContract;
use App\Modules\Manufacturing\Application\Contracts\WorkOrderRepositoryContract;
use App\Modules\Manufacturing\Infrastructure\Persistence\EloquentBomRepository;
use App\Modules\Manufacturing\Infrastructure\Persistence\EloquentWorkOrderRepository;
use Illuminate\Support\ServiceProvider;

final class ManufacturingServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        BomRepositoryContract::class       => EloquentBomRepository::class,
        WorkOrderRepositoryContract::class => EloquentWorkOrderRepository::class,
    ];

    public function register(): void
    {
        // Bindings resolved automatically via $bindings array above.
    }

    public function boot(): void
    {
        // Register event listeners for WorkOrderCompleted, ProductionRunPosted
        // when downstream listeners (e.g. notifications, reporting) are added.
    }
}
