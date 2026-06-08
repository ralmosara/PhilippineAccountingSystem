<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\ValueObjects;

/**
 * Type of stock movement — determines sign and whether moving-avg cost
 * should be updated.
 */
enum MovementType: string
{
    case Receipt      = 'receipt';        // inbound (purchase / GRN)
    case Issue        = 'issue';          // outbound (sale / consumption)
    case TransferIn   = 'transfer_in';
    case TransferOut  = 'transfer_out';
    case Adjustment   = 'adjustment';     // sign indicated by quantity
    case Production   = 'production';     // FG output
    case Consumption  = 'consumption';    // raw material used
    case Return       = 'return';         // customer return → inbound

    public function isInbound(): bool
    {
        return match ($this) {
            self::Receipt, self::TransferIn, self::Production, self::Return => true,
            self::Issue, self::TransferOut, self::Consumption               => false,
            self::Adjustment                                                 => true,  // by quantity sign
        };
    }

    /** Whether this movement updates the item's moving-avg cost. */
    public function affectsMovingAverage(): bool
    {
        // Receipts and production add new units with a (possibly different) cost.
        // Issues consume at the current average — they don't shift it.
        return match ($this) {
            self::Receipt, self::TransferIn, self::Production, self::Return, self::Adjustment => true,
            default => false,
        };
    }
}
