<?php

declare(strict_types=1);

namespace App\Modules\Hr\Infrastructure\Providers;

use App\Modules\Hr\Application\Contracts\EmployeeRepositoryContract;
use App\Modules\Hr\Application\Contracts\LeaveRequestRepositoryContract;
use App\Modules\Hr\Infrastructure\Persistence\EloquentEmployeeRepository;
use App\Modules\Hr\Infrastructure\Persistence\EloquentLeaveRequestRepository;
use Illuminate\Support\ServiceProvider;

final class HrServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        EmployeeRepositoryContract::class      => EloquentEmployeeRepository::class,
        LeaveRequestRepositoryContract::class  => EloquentLeaveRequestRepository::class,
    ];

    public function register(): void
    {
    }

    public function boot(): void
    {
    }
}
