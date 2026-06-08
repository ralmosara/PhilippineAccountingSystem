<?php

declare(strict_types=1);

namespace App\Modules\FixedAssets\Domain\Events;

/**
 * Fired after a new fixed asset is saved to the repository.
 */
final readonly class AssetRegistered
{
    public function __construct(
        public string $assetId,
        public string $companyId,
        public string $assetNo,
        public string $name,
        public string $category,
        public string $acquisitionCost,
        public string $registeredBy,
        public \DateTimeImmutable $registeredAt,
    ) {
    }
}
