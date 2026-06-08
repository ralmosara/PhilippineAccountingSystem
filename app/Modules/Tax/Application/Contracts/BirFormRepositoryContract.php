<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\Contracts;

use App\Modules\Tax\Domain\Entities\BirForm;
use App\Modules\Tax\Domain\ValueObjects\BirFormId;
use App\Modules\Tax\Domain\ValueObjects\FormPeriod;

interface BirFormRepositoryContract
{
    public function findById(BirFormId $id): ?BirForm;

    public function findByPeriod(string $companyId, string $formType, FormPeriod $period): ?BirForm;

    public function save(BirForm $form): void;
}
