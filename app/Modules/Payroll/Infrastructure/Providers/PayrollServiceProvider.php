<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Providers;

use App\Modules\Payroll\Application\Contracts\CompensationProviderContract;
use App\Modules\Payroll\Application\Contracts\LoanDeductionRepositoryContract;
use App\Modules\Payroll\Application\Contracts\PayrollRunRepositoryContract;
use App\Modules\Payroll\Application\Contracts\StatutoryRateProviderContract;
use App\Modules\Payroll\Application\Contracts\StatutoryRemittanceAggregatorContract;
use App\Modules\Payroll\Infrastructure\Persistence\EloquentCompensationProvider;
use App\Modules\Payroll\Infrastructure\Persistence\EloquentLoanDeductionRepository;
use App\Modules\Payroll\Infrastructure\Persistence\EloquentPayrollRunRepository;
use App\Modules\Payroll\Infrastructure\Persistence\EloquentStatutoryRateProvider;
use App\Modules\Payroll\Infrastructure\Persistence\EloquentStatutoryRemittanceAggregator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\ServiceProvider;

final class PayrollServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        PayrollRunRepositoryContract::class      => EloquentPayrollRunRepository::class,
        LoanDeductionRepositoryContract::class   => EloquentLoanDeductionRepository::class,
    ];

    public function register(): void
    {
        $this->app->singleton(
            CompensationProviderContract::class,
            fn ($app) => new EloquentCompensationProvider($app->make(ConnectionInterface::class))
        );

        $this->app->singleton(
            StatutoryRateProviderContract::class,
            fn ($app) => new EloquentStatutoryRateProvider($app->make(ConnectionInterface::class))
        );

        $this->app->singleton(
            StatutoryRemittanceAggregatorContract::class,
            fn ($app) => new EloquentStatutoryRemittanceAggregator($app->make(ConnectionInterface::class))
        );
    }

    public function boot(): void
    {
    }
}
