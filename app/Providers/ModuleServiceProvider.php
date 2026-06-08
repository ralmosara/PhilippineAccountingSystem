<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Finder\Finder;

/**
 * ModuleServiceProvider — auto-discovers and wires up DDD modules.
 *
 * Each bounded context lives at `app/Modules/<Name>/` and may expose:
 *   - `routes.php`              → mounted under /api/v1
 *   - `database/migrations/`    → registered as a migration path
 *   - `Infrastructure/Providers/<Name>ServiceProvider.php` → loaded automatically
 *   - `Application/Contracts/`  → public surface (the only thing other modules may import)
 *
 * This avoids hand-editing config/app.php every time a module is added or
 * extracted, and keeps module boundaries explicit.
 */
final class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Modules in load order. Identity is first because everything else
     * depends on `auth` and `permission` middleware aliases registered there.
     */
    private const MODULE_LOAD_ORDER = [
        'Identity',
        'Audit',
        'Accounting',
        'FixedAssets',
        'Sales',
        'Inventory',
        'Procurement',
        'Hr',
        'Payroll',
        'Projects',
        'Manufacturing',
        'Tax',
        'Reporting',
    ];

    public function register(): void
    {
        foreach ($this->discoverModules() as $module => $modulePath) {
            $providerClass = "App\\Modules\\{$module}\\Infrastructure\\Providers\\{$module}ServiceProvider";

            if (class_exists($providerClass)) {
                $this->app->register($providerClass);
            }
        }
    }

    public function boot(): void
    {
        foreach ($this->discoverModules() as $module => $modulePath) {
            $this->loadModuleRoutes($module, $modulePath);
            $this->loadModuleMigrations($modulePath);
            $this->loadModuleTranslations($module, $modulePath);
            $this->loadModuleViews($module, $modulePath);
        }
    }

    /**
     * Walk app/Modules in defined order, ignoring directories not
     * present (so partial checkouts and pre-extraction states still boot).
     *
     * @return array<string, string>  module name → absolute path
     */
    private function discoverModules(): array
    {
        $base = app_path('Modules');

        if (! is_dir($base)) {
            return [];
        }

        $modules = [];

        foreach (self::MODULE_LOAD_ORDER as $name) {
            $path = $base.DIRECTORY_SEPARATOR.$name;

            if (is_dir($path)) {
                $modules[$name] = $path;
            }
        }

        return $modules;
    }

    private function loadModuleRoutes(string $module, string $path): void
    {
        $routesFile = $path.'/routes.php';

        if (! file_exists($routesFile)) {
            return;
        }

        Route::middleware('api')
            ->prefix('api/v1')
            ->group($routesFile);
    }

    private function loadModuleMigrations(string $path): void
    {
        $migrationsPath = $path.'/database/migrations';

        if (is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }
    }

    private function loadModuleTranslations(string $module, string $path): void
    {
        $langPath = $path.'/resources/lang';

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, strtolower($module));
        }
    }

    private function loadModuleViews(string $module, string $path): void
    {
        $viewsPath = $path.'/resources/views';

        if (is_dir($viewsPath)) {
            $this->loadViewsFrom($viewsPath, strtolower($module));
        }
    }
}
