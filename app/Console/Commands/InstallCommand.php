<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * `php artisan pha:install`
 *
 * One-shot bootstrap: verify DB connectivity + extensions, run migrations,
 * run seeders, verify the audit hash chain works end-to-end, print a
 * BIR-compliance posture summary.
 */
final class InstallCommand extends Command
{
    protected $signature = 'pha:install
                            {--fresh : Drop all tables first (destructive — dev only)}
                            {--no-seed : Skip the demo data seeder}';

    protected $description = 'Bootstrap the Philippine Accounting System database (init, migrate, seed, verify).';

    public function handle(): int
    {
        $this->components->info('PHA installer starting…');

        try {
            $this->verifyDatabase();
            $this->verifyExtensions();
            $this->verifySchemas();
            $this->runMigrations();

            if (! $this->option('no-seed')) {
                $this->runSeeders();
            }

            $this->verifyAuditChain();
            $this->printPosture();
        } catch (Throwable $e) {
            $this->components->error('Install failed: '.$e->getMessage());
            $this->line('  → '.$e->getFile().':'.$e->getLine());
            return self::FAILURE;
        }

        $this->components->info('Done. Sign in at http://localhost:5173 with admin@pha.local / Mypass123');
        return self::SUCCESS;
    }

    private function verifyDatabase(): void
    {
        $this->components->task('Connecting to database', function () {
            DB::connection()->getPdo();
            return true;
        });
    }

    private function verifyExtensions(): void
    {
        $required = ['pgcrypto', 'citext', 'ltree', 'pg_trgm', 'unaccent', 'uuid-ossp'];
        $installed = collect(DB::select("SELECT extname FROM pg_extension"))->pluck('extname')->all();

        foreach ($required as $ext) {
            $this->components->task("Extension {$ext}", fn () => in_array($ext, $installed, true));
        }
    }

    private function verifySchemas(): void
    {
        $required = [
            'identity', 'accounting', 'sales', 'inventory', 'procurement',
            'payroll', 'hr', 'projects', 'manufacturing', 'tax', 'reporting', 'audit',
        ];
        $found = collect(DB::select(
            "SELECT schema_name FROM information_schema.schemata WHERE schema_name = ANY(?::text[])",
            ['{'.implode(',', $required).'}']
        ))->pluck('schema_name')->all();

        $missing = array_diff($required, $found);
        if ($missing !== []) {
            throw new \RuntimeException(
                'Missing schemas: '.implode(', ', $missing).
                '. Did you run docker/postgres/init.sql?'
            );
        }

        $this->components->task('All 12 schemas present', fn () => true);
    }

    private function runMigrations(): void
    {
        if ($this->option('fresh')) {
            $this->components->warn('Running migrate:fresh — this drops every table.');
            Artisan::call('migrate:fresh', ['--force' => true], $this->output);
        } else {
            Artisan::call('migrate', ['--force' => true], $this->output);
        }
    }

    private function runSeeders(): void
    {
        Artisan::call('db:seed', ['--force' => true], $this->output);
    }

    private function verifyAuditChain(): void
    {
        $this->components->task('Audit chain trigger', function () {
            // The chain is verified by inserting two test events through the
            // SECURITY DEFINER function and confirming the second's prev_hash
            // matches the first's current_hash.
            DB::transaction(function () {
                $companyId = '018f0000-0000-7000-8000-000000000999';

                $id1 = DB::scalar(
                    "SELECT audit.write_event(NULL, ?::uuid, 'install.test', 'Install', ?::uuid, ?::jsonb)",
                    [$companyId, $companyId, '{"step":1}'],
                );
                $id2 = DB::scalar(
                    "SELECT audit.write_event(NULL, ?::uuid, 'install.test', 'Install', ?::uuid, ?::jsonb)",
                    [$companyId, $companyId, '{"step":2}'],
                );

                $row1 = DB::selectOne('SELECT current_hash FROM audit.events WHERE id = ?', [$id1]);
                $row2 = DB::selectOne('SELECT prev_hash FROM audit.events WHERE id = ?', [$id2]);

                if ($row1->current_hash !== $row2->prev_hash) {
                    throw new \RuntimeException('Audit chain trigger malfunction.');
                }

                // We deliberately do NOT clean these up (audit is append-only;
                // they remain as evidence the chain works on this install).
            });
            return true;
        });
    }

    private function printPosture(): void
    {
        $this->newLine();
        $this->line('  <fg=green>BIR Compliance Posture</>');
        $this->line('  ─────────────────────────────────────────────');
        $this->line('  Audit chain          : <fg=green>Active</>     (BIR CAS RR 9-2009 §6.2)');
        $this->line('  Period locking       : <fg=yellow>Pending</>    (Accounting module)');
        $this->line('  Sequential numbering : <fg=yellow>Pending</>    (Sales / Accounting modules)');
        $this->line('  EIS gateway          : <fg=yellow>Disabled</>   (set BIR_EIS_ENABLED=true after sandbox creds)');
        $this->line('  e-Sales reporting    : <fg=yellow>Pending</>    (Tax module)');
        $this->newLine();
    }
}
