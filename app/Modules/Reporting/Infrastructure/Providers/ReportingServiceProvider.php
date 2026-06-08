<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Infrastructure\Providers;

use App\Modules\Reporting\Application\Contracts\BooksOfAccountsAggregatorContract;
use App\Modules\Reporting\Application\Contracts\ReportDataAggregatorContract;
use App\Modules\Reporting\Application\Contracts\ReportPdfRendererContract;
use App\Modules\Reporting\Application\Contracts\ReportRepositoryContract;
use App\Modules\Reporting\Infrastructure\Pdf\DomPdfReportRenderer;
use App\Modules\Reporting\Infrastructure\Persistence\EloquentBooksOfAccountsAggregator;
use App\Modules\Reporting\Infrastructure\Persistence\EloquentReportDataAggregator;
use App\Modules\Reporting\Infrastructure\Persistence\EloquentReportRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\ServiceProvider;

final class ReportingServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        ReportRepositoryContract::class    => EloquentReportRepository::class,
        ReportPdfRendererContract::class   => DomPdfReportRenderer::class,
    ];

    public function register(): void
    {
        $this->app->singleton(
            ReportDataAggregatorContract::class,
            fn ($app) => new EloquentReportDataAggregator($app->make(ConnectionInterface::class))
        );

        $this->app->singleton(
            BooksOfAccountsAggregatorContract::class,
            fn ($app) => new EloquentBooksOfAccountsAggregator($app->make(ConnectionInterface::class))
        );
    }

    public function boot(): void
    {
    }
}
