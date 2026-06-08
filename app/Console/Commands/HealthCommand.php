<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * `php artisan pha:health`
 *
 * Cheap end-to-end health check covering the integrations PHA depends on:
 * Postgres, Redis, MinIO/S3. Useful as a pre-flight before going on-call.
 */
final class HealthCommand extends Command
{
    protected $signature = 'pha:health';

    protected $description = 'Probe Postgres, Redis, MinIO, and report status.';

    public function handle(): int
    {
        $allOk = true;

        $allOk = $this->probe('PostgreSQL', function () {
            DB::select('SELECT 1');
            $count = DB::scalar('SELECT count(*) FROM information_schema.schemata WHERE schema_name = ANY(?::text[])', [
                '{identity,accounting,sales,inventory,procurement,payroll,hr,projects,manufacturing,tax,reporting,audit}',
            ]);
            return "{$count}/12 schemas";
        }) && $allOk;

        $allOk = $this->probe('Redis', function () {
            Cache::store('redis')->put('pha:health:probe', 1, 5);
            return Cache::store('redis')->get('pha:health:probe') === 1 ? 'ok' : 'mismatch';
        }) && $allOk;

        $allOk = $this->probe('MinIO / S3', function () {
            Storage::disk(config('filesystems.default'))->put('health/probe.txt', 'ok');
            return 'ok';
        }) && $allOk;

        return $allOk ? self::SUCCESS : self::FAILURE;
    }

    private function probe(string $name, callable $check): bool
    {
        try {
            $result = $check();
            $this->line(sprintf('  <fg=green>✓</> %-15s %s', $name, $result));
            return true;
        } catch (Throwable $e) {
            $this->line(sprintf('  <fg=red>✗</> %-15s %s', $name, $e->getMessage()));
            return false;
        }
    }
}
