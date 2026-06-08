<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Use the Laravel migration suite + a per-test transactional rollback.
     * Feature tests inheriting this class get a clean Postgres for every it().
     *
     * Override `usesDatabase()` to skip — useful for tests that don't need a DB
     * but still want the framework boot.
     */
    protected function setUp(): void
    {
        parent::setUp();
        if ($this->usesDatabase()) {
            $this->refreshDatabase();
        }
    }

    protected function usesDatabase(): bool
    {
        return true;
    }

    private function refreshDatabase(): void
    {
        // Compose RefreshDatabase's behaviour without polluting the class
        // hierarchy — Pest's `uses(TestCase::class)` already imports us into
        // Feature tests, so we can't trait-mix here easily.
        $this->beforeApplicationDestroyed(static function () {});
        if (! \Illuminate\Support\Facades\DB::connection()->getDriverName()) {
            return;
        }
        \Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true, '--seed' => false]);
        \Illuminate\Support\Facades\DB::beginTransaction();
        $this->beforeApplicationDestroyed(static function () {
            try {
                \Illuminate\Support\Facades\DB::rollBack();
            } catch (\Throwable $e) {
                // connection may already be down
            }
        });
    }
}
