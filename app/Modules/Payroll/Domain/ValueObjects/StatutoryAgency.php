<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * The three statutory contribution agencies that employer remittance files
 * are filed with. The enum doubles as the dispatch key for which formatter
 * builds the agency-specific file (SSS = R-3 CSV, PhilHealth = RF-1 CSV,
 * Pag-IBIG = MCRF CSV).
 */
enum StatutoryAgency: string
{
    case Sss       = 'sss';
    case PhilHealth = 'philhealth';
    case PagIbig    = 'pagibig';

    public function formCode(): string
    {
        return match ($this) {
            self::Sss        => 'R-3',
            self::PhilHealth => 'RF-1',
            self::PagIbig    => 'MCRF',
        };
    }

    public function fileExtension(): string
    {
        // Each agency provides an uploader portal that accepts their own
        // tabular format. CSV is the lowest common denominator that all
        // three currently accept; SSS additionally has a fixed-width legacy
        // format which we ignore (RMS portal accepts CSV since 2020).
        return 'csv';
    }

    /** Friendly display label for receipts / file names. */
    public function label(): string
    {
        return match ($this) {
            self::Sss        => 'SSS R-3',
            self::PhilHealth => 'PhilHealth RF-1',
            self::PagIbig    => 'Pag-IBIG MCRF',
        };
    }

    public static function fromString(string $value): self
    {
        return self::tryFrom(strtolower($value))
            ?? throw new InvalidArgumentException("Unknown statutory agency '{$value}'.");
    }
}
