<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class CustomerAddressModel extends Model
{
    use HasUuids;

    protected $table = 'sales.customer_addresses';

    /** @var array<int, string> */
    protected $fillable = [
        'customer_id', 'address_type', 'line1', 'line2',
        'barangay', 'city', 'province', 'region', 'postal_code', 'is_default',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }
}
