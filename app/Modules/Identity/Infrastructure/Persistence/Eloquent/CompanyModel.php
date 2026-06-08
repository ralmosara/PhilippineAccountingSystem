<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CompanyModel extends Model
{
    use HasUuids;

    protected $table = 'identity.companies';

    /** @var array<int, string> */
    protected $fillable = [
        'tin', 'rdo_code', 'registered_name', 'trade_name',
        'taxpayer_type', 'vat_status', 'address', 'telephone', 'email',
        'registered_on', 'cas_ptu_number', 'cas_ptu_date',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'registered_on' => 'date',
            'cas_ptu_date'  => 'date',
        ];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(BranchModel::class, 'company_id');
    }
}
