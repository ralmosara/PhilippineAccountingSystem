<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Providers;

use App\Modules\Accounting\Application\Contracts\AccountRepositoryContract;
use App\Modules\Accounting\Application\Contracts\FiscalPeriodRepositoryContract;
use App\Modules\Accounting\Application\Contracts\JournalRepositoryContract;
use App\Modules\Accounting\Infrastructure\Persistence\EloquentAccountRepository;
use App\Modules\Accounting\Infrastructure\Persistence\EloquentFiscalPeriodRepository;
use App\Modules\Accounting\Infrastructure\Persistence\EloquentJournalRepository;
use Illuminate\Support\ServiceProvider;

final class AccountingServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        JournalRepositoryContract::class      => EloquentJournalRepository::class,
        AccountRepositoryContract::class      => EloquentAccountRepository::class,
        FiscalPeriodRepositoryContract::class => EloquentFiscalPeriodRepository::class,
    ];

    public function register(): void
    {
        // bindings auto-resolved
    }

    public function boot(): void
    {
        // Register listeners on JournalPosted etc. here when subscribers exist.
    }
}
