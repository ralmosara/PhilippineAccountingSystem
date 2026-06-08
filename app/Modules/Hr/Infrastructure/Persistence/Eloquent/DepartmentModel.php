<?php

declare(strict_types=1);

namespace App\Modules\Hr\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class DepartmentModel extends Model
{
    use HasUuids;

    protected $table = 'hr.departments';

    /** @var array<int, string> */
    protected $fillable = ['company_id', 'code', 'name', 'parent_id', 'path', 'head_employee_id', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
