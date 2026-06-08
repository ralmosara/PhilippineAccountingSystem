<?php

declare(strict_types=1);

namespace App\Modules\Hr\Presentation\Http\Resources;

use App\Modules\Hr\Infrastructure\Persistence\Eloquent\PositionModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PositionModel */
final class PositionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'code'              => $this->code,
            'title'             => $this->title,
            'department_id'     => $this->department_id,
            'salary_grade_min'  => $this->salary_grade_min,
            'salary_grade_max'  => $this->salary_grade_max,
            'is_active'         => (bool) $this->is_active,
        ];
    }
}
