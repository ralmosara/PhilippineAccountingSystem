<?php

declare(strict_types=1);

namespace App\Modules\Tax\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class BirFormLineModel extends Model
{
    use HasUuids;

    protected $table = 'tax.bir_form_lines';

    /** @var array<int, string> */
    protected $fillable = ['bir_form_id', 'line_code', 'description', 'amount', 'breakdown'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount'    => 'decimal:2',
            'breakdown' => 'array',
        ];
    }
}
