<?php

declare(strict_types=1);

namespace App\Modules\Projects\Infrastructure\Providers;

use App\Modules\Projects\Application\Contracts\ProjectRepositoryContract;
use App\Modules\Projects\Application\Contracts\TimesheetRepositoryContract;
use App\Modules\Projects\Application\Contracts\WipRepositoryContract;
use App\Modules\Projects\Infrastructure\Persistence\EloquentProjectRepository;
use App\Modules\Projects\Infrastructure\Persistence\EloquentTimesheetRepository;
use App\Modules\Projects\Infrastructure\Persistence\EloquentWipRepository;
use Illuminate\Support\ServiceProvider;

final class ProjectsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProjectRepositoryContract::class, EloquentProjectRepository::class);
        $this->app->bind(TimesheetRepositoryContract::class, EloquentTimesheetRepository::class);
        $this->app->bind(WipRepositoryContract::class, EloquentWipRepository::class);
    }

    public function boot(): void
    {
    }
}
