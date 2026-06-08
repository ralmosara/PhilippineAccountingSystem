<?php

declare(strict_types=1);

namespace App\Modules\FixedAssets\Domain\Entities;

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\FixedAssets\Domain\ValueObjects\AssetCategory;
use App\Modules\FixedAssets\Domain\ValueObjects\AssetId;
use App\Modules\FixedAssets\Domain\ValueObjects\DepreciationMethod;
use DateTimeImmutable;
use DomainException;

/**
 * FixedAsset — the core aggregate for the Fixed Assets bounded context.
 *
 * All monetary arithmetic uses BCMath via the Money value object.
 * Land assets have useful_life_months = 0 and never depreciate.
 */
final class FixedAsset
{
    public string $status = 'active';

    public ?DateTimeImmutable $disposedAt         = null;
    public ?string            $disposalProceeds   = null;  // numeric string
    public ?string            $disposalGainLoss   = null;  // numeric string
    public ?string            $disposalJournalEntryId = null;

    public function __construct(
        public readonly AssetId           $id,
        public readonly string            $companyId,
        public readonly string            $assetNo,
        public string                     $name,
        public ?string                    $description,
        public readonly AssetCategory     $category,
        public readonly DateTimeImmutable $acquisitionDate,
        public readonly string            $acquisitionCost,    // numeric(18,2) string
        public string                     $salvageValue,        // numeric(18,2) string, default '0.00'
        public readonly int               $usefulLifeMonths,
        public readonly DepreciationMethod $depreciationMethod,
        public string                     $accumulatedDepreciation, // numeric(18,2) string
        public string                     $bookValue,               // numeric(18,2) string
        public ?string                    $branchId       = null,
        public ?string                    $costCenterId   = null,
        public ?string                    $assetAccountId = null,
        public ?string                    $accumDeprAccountId  = null,
        public ?string                    $deprExpenseAccountId = null,
    ) {
    }

    /**
     * Calculate this period's depreciation amount.
     *
     * Straight-Line:          (cost - salvage) / useful_life_months
     * Double-Declining Balance: 2 * (1 / useful_life_months) * book_value
     * Land (useful_life = 0): always zero
     */
    public function computeMonthlyDepreciation(): Money
    {
        if ($this->usefulLifeMonths === 0) {
            return Money::zero();
        }

        if ($this->isFullyDepreciated()) {
            return Money::zero();
        }

        $lifeStr = (string) $this->usefulLifeMonths;

        $amount = match ($this->depreciationMethod) {
            DepreciationMethod::StraightLine => bcdiv(
                bcsub($this->acquisitionCost, $this->salvageValue, 4),
                $lifeStr,
                4
            ),
            DepreciationMethod::DoubleDecliningBalance => bcmul(
                bcmul('2', bcdiv('1', $lifeStr, 8), 8),
                $this->bookValue,
                4
            ),
        };

        // Cap at remaining depreciable amount so we never go below salvage
        $remaining = bcsub($this->bookValue, $this->salvageValue, 4);

        if (bccomp($amount, '0', 4) < 0) {
            $amount = '0.0000';
        }

        if (bccomp($amount, $remaining, 4) > 0) {
            $amount = $remaining;
        }

        return new Money($amount);
    }

    /**
     * Apply a depreciation charge: increment accumulated, reduce book value.
     */
    public function applyDepreciation(Money $amount): void
    {
        $this->accumulatedDepreciation = bcadd(
            $this->accumulatedDepreciation,
            $amount->toPhp(4),
            2
        );

        $this->bookValue = bcsub($this->bookValue, $amount->toPhp(4), 2);

        if ($this->isFullyDepreciated()) {
            $this->status = 'fully_depreciated';
        }
    }

    /**
     * Returns true when book value has reached or fallen to salvage value.
     */
    public function isFullyDepreciated(): bool
    {
        return bccomp($this->bookValue, $this->salvageValue, 2) <= 0;
    }

    /**
     * Mark the asset as disposed, compute gain/loss on disposal.
     *
     * Gain/Loss = proceeds - book_value
     * Positive → gain, Negative → loss.
     */
    public function dispose(Money $proceeds, DateTimeImmutable $at): void
    {
        if ($this->status === 'disposed') {
            throw new DomainException("Asset {$this->assetNo} is already disposed.");
        }

        $gainLoss = bcsub($proceeds->toPhp(4), $this->bookValue, 2);

        $this->status             = 'disposed';
        $this->disposedAt         = $at;
        $this->disposalProceeds   = $proceeds->toPhp(2);
        $this->disposalGainLoss   = $gainLoss;
    }
}
