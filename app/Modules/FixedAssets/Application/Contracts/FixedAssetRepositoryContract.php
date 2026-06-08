<?php

declare(strict_types=1);

namespace App\Modules\FixedAssets\Application\Contracts;

use App\Modules\FixedAssets\Domain\Entities\FixedAsset;
use App\Modules\FixedAssets\Domain\ValueObjects\AssetId;

/**
 * Public surface of the Fixed Assets persistence layer.
 * Other modules must not import the Eloquent implementation directly.
 */
interface FixedAssetRepositoryContract
{
    public function findById(AssetId $id): ?FixedAsset;

    public function save(FixedAsset $asset): void;

    /**
     * Return all assets for a company, newest first.
     *
     * @return list<FixedAsset>
     */
    public function listForCompany(string $companyId): array;

    /**
     * Return active assets that have not been fully deprecated or disposed.
     *
     * @return list<FixedAsset>
     */
    public function listActiveForCompany(string $companyId): array;

    /**
     * Generate and reserve the next asset number for the company.
     * Format: FA-{YYYY}-{NNN}  (e.g. FA-2026-001)
     */
    public function nextAssetNo(string $companyId): string;
}
