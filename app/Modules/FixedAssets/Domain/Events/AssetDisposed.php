<?php

declare(strict_types=1);

namespace App\Modules\FixedAssets\Domain\Events;

/**
 * Fired after an asset disposal is recorded and the disposal JV is posted.
 */
final readonly class AssetDisposed
{
    public function __construct(
        public string $assetId,
        public string $companyId,
        public string $assetNo,
        public string $proceeds,       // numeric string
        public string $gainLoss,       // numeric string (positive=gain, negative=loss)
        public string $journalEntryId,
        public string $disposedBy,
        public \DateTimeImmutable $disposedAt,
    ) {
    }
}
