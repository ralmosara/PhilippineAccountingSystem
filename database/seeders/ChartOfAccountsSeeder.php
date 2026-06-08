<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Accounting\Domain\ValueObjects\AccountCode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * Seeds the demo company's Chart of Accounts from PFRS-for-SMEs template.
 * Idempotent — re-running upserts by (company_id, code).
 */
final class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $company = DB::table('identity.companies')->where('registered_name', 'ABC Trading Incorporated')->first();
        if (! $company) {
            $this->command?->warn('ChartOfAccountsSeeder: demo company not found; skipping.');
            return;
        }

        $template = json_decode(
            (string) file_get_contents(database_path('seed-data/coa-pfrs-sme.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        // Build code → uuid map first (for parent_id resolution)
        $codeToId = [];
        foreach ($template['accounts'] as $row) {
            $codeToId[$row['code']] = $this->stableUuid($company->id, $row['code']);
        }

        $now = now();
        $rows = [];

        foreach ($template['accounts'] as $row) {
            $rows[] = [
                'id'                  => $codeToId[$row['code']],
                'company_id'          => $company->id,
                'code'                => $row['code'],
                'name'                => $row['name'],
                'type'                => $row['type'],
                'normal_balance'      => $row['normal_balance'],
                'parent_id'           => $row['parent_code'] ? $codeToId[$row['parent_code']] : null,
                'path'                => (new AccountCode($row['code']))->toLtreePath(),
                'is_postable'         => $row['is_postable'],
                'is_active'           => true,
                'description'         => $row['name'],
                'pfrs_classification' => $row['pfrs_classification'],
                'created_at'          => $now,
                'updated_at'          => $now,
            ];
        }

        DB::table('accounting.accounts')->upsert(
            $rows,
            ['company_id', 'code'],
            ['name', 'type', 'normal_balance', 'parent_id', 'path', 'is_postable', 'pfrs_classification', 'updated_at'],
        );

        $this->command?->info(sprintf('Seeded %d Chart of Accounts entries (PFRS for SMEs).', count($rows)));
    }

    /**
     * Deterministic UUID v5 from (company_id, code) so re-seeds resolve to the
     * same parent_id without orphaning child accounts.
     */
    private function stableUuid(string $companyId, string $code): string
    {
        $namespace = Uuid::uuid5(Uuid::NAMESPACE_OID, 'pha.accounting.accounts');
        return Uuid::uuid5($namespace, "{$companyId}/{$code}")->toString();
    }
}
