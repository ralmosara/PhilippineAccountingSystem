<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Providers;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Audit\Infrastructure\Persistence\PostgresAuditWriter;
use App\Modules\Audit\Presentation\Console\VerifyAuditChainCommand;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\ServiceProvider;

final class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            AuditWriterContract::class,
            fn ($app) => new PostgresAuditWriter($app->make(ConnectionInterface::class))
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                VerifyAuditChainCommand::class,
            ]);
        }
    }
}
