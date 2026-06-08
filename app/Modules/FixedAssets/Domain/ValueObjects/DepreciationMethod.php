<?php

declare(strict_types=1);

namespace App\Modules\FixedAssets\Domain\ValueObjects;

/**
 * DepreciationMethod — backed enum for the two supported depreciation methods.
 *
 * Straight-Line (SLM): uniform charge each month over useful life.
 * Double-Declining Balance (DDB): accelerated method — 2x SLM rate on book value.
 */
enum DepreciationMethod: string
{
    case StraightLine          = 'straight_line';
    case DoubleDecliningBalance = 'double_declining_balance';

    public function label(): string
    {
        return match ($this) {
            self::StraightLine           => 'Straight-Line',
            self::DoubleDecliningBalance => 'Double-Declining Balance',
        };
    }
}
