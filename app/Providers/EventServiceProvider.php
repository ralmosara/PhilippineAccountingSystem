<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

final class EventServiceProvider extends ServiceProvider
{
    /** @var array<class-string, array<int, class-string>> */
    protected $listen = [];

    public function boot(): void
    {
        // Domain event listeners are registered per-module in their own
        // <Module>ServiceProvider via Event::listen(). This keeps the
        // top-level provider unburdened.
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
