<?php

declare(strict_types=1);

namespace App\Modules\FixedAssets\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\FixedAssets\Application\Contracts\FixedAssetRepositoryContract;
use App\Modules\FixedAssets\Domain\Events\DepreciationPosted;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * Post monthly depreciation for all active, non-fully-depreciated assets.
 *
 * Idempotent per (asset_id, year, month): if a depreciation_entry already
 * exists for the period, that asset is skipped.
 *
 * For each qualifying asset:
 *   1. Compute the depreciation amount (via domain method).
 *   2. Insert a JV: DR Depreciation Expense / CR Accumulated Depreciation.
 *   3. Insert a depreciation_entry row.
 *   4. Apply the charge to the asset aggregate and persist it.
 */
final readonly class ComputeMonthlyDepreciation
{
    public function __construct(
        private FixedAssetRepositoryContract $assets,
        private AuditWriterContract          $audit,
        private Dispatcher                   $events,
    ) {
    }

    public function execute(
        string $companyId,
        int    $year,
        int    $month,
        string $actorId,
    ): array {
        return DB::transaction(function () use ($companyId, $year, $month, $actorId) {
            $activeAssets = $this->assets->listActiveForCompany($companyId);

            $processed       = 0;
            $totalDepreciation = '0.00';

            foreach ($activeAssets as $asset) {
                // Idempotency check — skip if already posted for this period
                $exists = DB::table('assets.depreciation_entries')
                    ->where('fixed_asset_id', $asset->id->value)
                    ->where('period_year', $year)
                    ->where('period_month', $month)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $amount = $asset->computeMonthlyDepreciation();

                if ($amount->isZero()) {
                    continue;
                }

                $amountStr = $amount->toPhp(2);

                // Post JV if account IDs are configured on the asset
                $journalEntryId = null;
                if ($asset->deprExpenseAccountId && $asset->accumDeprAccountId) {
                    $journalEntryId = $this->insertJournalEntry(
                        companyId:          $companyId,
                        assetNo:            $asset->assetNo,
                        assetName:          $asset->name,
                        year:               $year,
                        month:              $month,
                        amount:             $amountStr,
                        deprExpenseAccount: $asset->deprExpenseAccountId,
                        accumDeprAccount:   $asset->accumDeprAccountId,
                        actorId:            $actorId,
                    );
                }

                // Compute running totals for the entry
                $accumulatedAfter = bcadd($asset->accumulatedDepreciation, $amountStr, 2);
                $bookValueAfter   = bcsub($asset->bookValue, $amountStr, 2);

                // Insert depreciation entry
                DB::table('assets.depreciation_entries')->insert([
                    'id'                 => Uuid::uuid4()->toString(),
                    'fixed_asset_id'     => $asset->id->value,
                    'period_year'        => $year,
                    'period_month'       => $month,
                    'depreciation_amount'=> $amountStr,
                    'accumulated_after'  => $accumulatedAfter,
                    'book_value_after'   => $bookValueAfter,
                    'journal_entry_id'   => $journalEntryId,
                    'posted_at'          => now(),
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);

                // Apply to domain aggregate and persist
                $asset->applyDepreciation($amount);
                $this->assets->save($asset);

                $totalDepreciation = bcadd($totalDepreciation, $amountStr, 2);
                $processed++;
            }

            if ($processed > 0) {
                $this->audit->writeEvent(
                    actorId:     $actorId,
                    companyId:   $companyId,
                    eventType:   'depreciation.posted',
                    aggregate:   'DepreciationBatch',
                    aggregateId: $companyId,
                    payload: [
                        'year'               => $year,
                        'month'              => $month,
                        'assets_processed'   => $processed,
                        'total_depreciation' => $totalDepreciation,
                    ],
                );

                $this->events->dispatch(new DepreciationPosted(
                    companyId:          $companyId,
                    year:               $year,
                    month:              $month,
                    assetsProcessed:    $processed,
                    totalDepreciation:  $totalDepreciation,
                    postedBy:           $actorId,
                    postedAt:           new DateTimeImmutable(),
                ));
            }

            return [
                'assets_processed'   => $processed,
                'total_depreciation' => $totalDepreciation,
                'year'               => $year,
                'month'              => $month,
            ];
        });
    }

    /**
     * Insert a minimal journal entry + two lines directly into the accounting schema.
     * Uses the same pattern as other modules that post JVs from outside Accounting.
     */
    private function insertJournalEntry(
        string $companyId,
        string $assetNo,
        string $assetName,
        int    $year,
        int    $month,
        string $amount,
        string $deprExpenseAccount,
        string $accumDeprAccount,
        string $actorId,
    ): string {
        $jeId  = Uuid::uuid4()->toString();
        $docNo = "DEPR-{$year}-".str_pad((string) $month, 2, '0', STR_PAD_LEFT)."-{$assetNo}";
        $entryDate = \DateTimeImmutable::createFromFormat('Y-n-j', "{$year}-{$month}-1");

        DB::table('accounting.journal_entries')->insert([
            'id'             => $jeId,
            'company_id'     => $companyId,
            'doc_no'         => $docNo,
            'entry_date'     => $entryDate->format('Y-m-01'),
            'memo'           => "Monthly depreciation — {$assetName} ({$assetNo}) {$year}/{$month}",
            'source'         => 'fixed_assets',
            'source_doc_id'  => null,
            'source_doc_type'=> 'DepreciationEntry',
            'posted_at'      => now(),
            'posted_by'      => $actorId,
            'created_by'     => $actorId,
            'updated_by'     => $actorId,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // Line 1: DR Depreciation Expense
        DB::table('accounting.journal_lines')->insert([
            'id'               => Uuid::uuid4()->toString(),
            'journal_entry_id' => $jeId,
            'line_no'          => 1,
            'account_id'       => $deprExpenseAccount,
            'debit'            => $amount,
            'credit'           => '0.00',
            'php_amount'       => $amount,
            'fx_rate'          => '1.0000',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        // Line 2: CR Accumulated Depreciation
        DB::table('accounting.journal_lines')->insert([
            'id'               => Uuid::uuid4()->toString(),
            'journal_entry_id' => $jeId,
            'line_no'          => 2,
            'account_id'       => $accumDeprAccount,
            'debit'            => '0.00',
            'credit'           => $amount,
            'php_amount'       => bcmul($amount, '-1', 2),
            'fx_rate'          => '1.0000',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return $jeId;
    }
}
