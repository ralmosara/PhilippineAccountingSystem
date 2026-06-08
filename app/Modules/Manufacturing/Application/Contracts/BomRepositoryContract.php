<?php

declare(strict_types=1);

namespace App\Modules\Manufacturing\Application\Contracts;

use App\Modules\Manufacturing\Domain\Entities\BillOfMaterials;
use App\Modules\Manufacturing\Domain\ValueObjects\BomId;

interface BomRepositoryContract
{
    public function findById(BomId $id): ?BillOfMaterials;

    public function findByCode(string $companyId, string $code): ?BillOfMaterials;

    public function save(BillOfMaterials $bom): void;

    /**
     * @return list<BillOfMaterials>
     */
    public function listForCompany(string $companyId, int $perPage = 25, int $page = 1): array;
}
