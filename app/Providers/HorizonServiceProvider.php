<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

final class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Horizon::routeMailNotificationsTo('ops@pha.local');
    }

    /**
     * Restrict access to the Horizon dashboard to Admin role with active MFA.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user) {
            return $user?->hasRole('Admin') && $user?->mfa_enabled;
        });
    }
}
