<?php

declare(strict_types=1);

use App\Http\Middleware\EnforceFiscalPeriodLock;
use App\Http\Middleware\EnsureMfaForSensitiveRoles;
use App\Http\Middleware\WriteAuditTrail;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        // Per-module API routes are wired by ModuleServiceProvider; the
        // top-level `routes/api.php` only carries shared concerns.
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();

        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'mfa'           => EnsureMfaForSensitiveRoles::class,
            'period.lock'   => EnforceFiscalPeriodLock::class,
            'audit'         => WriteAuditTrail::class,
            'role'          => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'    => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        // Sensitive endpoints (filing, posting, payroll approval) get MFA + audit
        $middleware->appendToGroup('api', [
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Render API errors as JSON consistently
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        // BIR-relevant exceptions are reported to Sentry with high priority
        $exceptions->reportable(function (\App\Exceptions\BirComplianceException $e) {
            // delegated to Sentry via SentryHandler
        });
    })
    ->create();
