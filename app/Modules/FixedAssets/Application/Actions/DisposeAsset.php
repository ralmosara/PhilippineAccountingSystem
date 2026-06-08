<?php

declare(strict_types=1);

namespace App\Modules\FixedAssets\Application\Actions;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\FixedAssets\Application\Contracts\FixedAssetRepositoryContract;
use App\Modules\FixedAssets\Domain\Events\AssetDisposed;
use App\Modules\FixedAssets\Domain\ValueObjects\AssetId;
use DateTimeImmutable;
use DomainException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * Dispose of a fixed asset.
 *
 * Disposal JV (all amounts positive; debit/credit column determines sign):
 *   DR  Accumulated Depreciation          (full accumulated amount)
 *   DR  Cash / Receivable                 (proceeds)
 *   DR/CR Gain-Loss on Disposal           (negative = DR Loss; positive = CR Gain)
 *   CR  Asset Cost (PPE Account)          (original acquisition cost)
 *
 * After posting:
 *   - Asset status → 'disposed', disposal_gain_loss, disposal_proceeds set.
 *   - Audits 'asset.disposed'.
 */
final readonly class DisposeAsset
{
    public function __construct(
        private FixedAssetRepositoryContract $assets,
        private AuditWriterContract          $audit,
        private Dispatcher                   $events,
    ) {
    }

    /**
     * @param  array{
     *     cash_account_id: string,
     *     gain_loss_account_id: string,
     * }  $accountIds
     */
    public function execute(
        string             $assetId,
        string             $proceeds,
        string             $disposalDate,
        array              $accountIds,
        string             $actorId,
    ): void {
        DB::transaction(function () use ($assetId, $proceeds, $disposalDate, $accountIds, $actorId) {
            $asset = $this->assets->findById(new AssetId($assetId))
                ?? throw new DomainException("Fixed asset not found: {$assetId}");

            if ($asset->status === 'disposed') {
                throw new DomainException("Asset {$asset->assetNo} is already disposed.");
            }

            $proceedsMoney = new Money($proceeds);
            $disposedAt    = new DateTimeImmutable($disposalDate);

            // Gain/loss = proceeds - book_value
            $gainLoss = bcsub($proceeds, $asset->bookValue, 2);

            // Build and post the disposal JV
            $journalEntryId = $this->insertDisposalJournalEntry(
                companyId:            $asset->companyId,
                assetNo:              $asset->assetNo,
                assetName:            $asset->name,
                acquisitionCost:      $asset->acquisitionCost,
                accumulatedDepr:      $asset->accumulatedDepreciation,
                bookValue:            $asset->bookValue,
                proceeds:             $proceeds,
                gainLoss:             $gainLoss,
                disposalDate:         $disposedAt,
                assetAccountId:       $asset->assetAccountId,
                accumDeprAccountId:   $asset->accumDeprAccountId,
                cashAccountId:        $accountIds['cash_account_id'],
                gainLossAccountId:    $accountIds['gain_loss_account_id'],
                actorId:              $actorId,
            );

            // Apply domain disposal
            $asset->dispose($proceedsMoney, $disposedAt);
            $asset->disposalJournalEntryId = $journalEntryId;

            $this->assets->save($asset);

            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $asset->companyId,
                eventType:   'asset.disposed',
                aggregate:   'FixedAsset',
                aggregateId: $asset->id->value,
                payload: [
                    'asset_no'         => $asset->assetNo,
                    'disposal_date'    => $disposedAt->format('Y-m-d'),
                    'proceeds'         => $proceeds,
                    'book_value'       => $asset->bookValue,
                    'gain_loss'        => $gainLoss,
                    'journal_entry_id' => $journalEntryId,
                ],
            );

            $this->events->dispatch(new AssetDisposed(
                assetId:        $asset->id->value,
                companyId:      $asset->companyId,
                assetNo:        $asset->assetNo,
                proceeds:       $proceeds,
                gainLoss:       $gainLoss,
                journalEntryId: $journalEntryId,
                disposedBy:     $actorId,
                disposedAt:     $disposedAt,
            ));
        });
    }

    private function insertDisposalJournalEntry(
        string            $companyId,
        string            $assetNo,
        string            $assetName,
        string            $acquisitionCost,
        string            $accumulatedDepr,
        string            $bookValue,
        string            $proceeds,
        string            $gainLoss,
        DateTimeImmutable $disposalDate,
        ?string           $assetAccountId,
        ?string           $accumDeprAccountId,
        string            $cashAccountId,
        string            $gainLossAccountId,
        string            $actorId,
    ): string {
        $jeId  = Uuid::uuid4()->toString();
        $docNo = "DISP-{$assetNo}-".$disposalDate->format('Ymd');

        DB::table('accounting.journal_entries')->insert([
            'id'              => $jeId,
            'company_id'      => $companyId,
            'doc_no'          => $docNo,
            'entry_date'      => $disposalDate->format('Y-m-d'),
            'memo'            => "Asset disposal — {$assetName} ({$assetNo})",
            'source'          => 'fixed_assets',
            'source_doc_id'   => null,
            'source_doc_type' => 'AssetDisposal',
            'posted_at'       => now(),
            'posted_by'       => $actorId,
            'created_by'      => $actorId,
            'updated_by'      => $actorId,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $lineNo = 1;

        // DR Accumulated Depreciation (clear it)
        if ($accumDeprAccountId && bccomp($accumulatedDepr, '0', 2) > 0) {
            DB::table('accounting.journal_lines')->insert([
                'id'               => Uuid::uuid4()->toString(),
                'journal_entry_id' => $jeId,
                'line_no'          => $lineNo++,
                'account_id'       => $accumDeprAccountId,
                'debit'            => $accumulatedDepr,
                'credit'           => '0.00',
                'php_amount'       => $accumulatedDepr,
                'fx_rate'          => '1.0000',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        // DR Cash / Receivable (proceeds received)
        if (bccomp($proceeds, '0', 2) > 0) {
            DB::table('accounting.journal_lines')->insert([
                'id'               => Uuid::uuid4()->toString(),
                'journal_entry_id' => $jeId,
                'line_no'          => $lineNo++,
                'account_id'       => $cashAccountId,
                'debit'            => $proceeds,
                'credit'           => '0.00',
                'php_amount'       => $proceeds,
                'fx_rate'          => '1.0000',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        // Gain/Loss on Disposal
        // Positive gainLoss → Gain → CR Gain account
        // Negative gainLoss → Loss → DR Loss account
        $gainLossCmp = bccomp($gainLoss, '0', 2);
        if ($gainLossCmp !== 0) {
            $absGainLoss = ltrim($gainLoss, '-');
            if ($gainLossCmp > 0) {
                // CR Gain on Disposal
                DB::table('accounting.journal_lines')->insert([
                    'id'               => Uuid::uuid4()->toString(),
                    'journal_entry_id' => $jeId,
                    'line_no'          => $lineNo++,
                    'account_id'       => $gainLossAccountId,
                    'debit'            => '0.00',
                    'credit'           => $absGainLoss,
                    'php_amount'       => bcmul($absGainLoss, '-1', 2),
                    'fx_rate'          => '1.0000',
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            } else {
                // DR Loss on Disposal
                DB::table('accounting.journal_lines')->insert([
                    'id'               => Uuid::uuid4()->toString(),
                    'journal_entry_id' => $jeId,
                    'line_no'          => $lineNo++,
                    'account_id'       => $gainLossAccountId,
                    'debit'            => $absGainLoss,
                    'credit'           => '0.00',
                    'php_amount'       => $absGainLoss,
                    'fx_rate'          => '1.0000',
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }
        }

        // CR Asset Cost (PPE Account — remove original cost)
        if ($assetAccountId) {
            DB::table('accounting.journal_lines')->insert([
                'id'               => Uuid::uuid4()->toString(),
                'journal_entry_id' => $jeId,
                'line_no'          => $lineNo,
                'account_id'       => $assetAccountId,
                'debit'            => '0.00',
                'credit'           => $acquisitionCost,
                'php_amount'       => bcmul($acquisitionCost, '-1', 2),
                'fx_rate'          => '1.0000',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        return $jeId;
    }
}
