<?php

declare(strict_types=1);

namespace App\Modules\Tax\Infrastructure\Persistence;

use App\Modules\Procurement\Application\Contracts\AtcCodeProviderContract;
use App\Modules\Tax\Infrastructure\Persistence\Eloquent\AtcCodeModel;
use Illuminate\Support\Facades\Cache;

/**
 * Tax module's implementation of Procurement's AtcCodeProviderContract.
 *
 * The contract LIVES IN the Procurement module's Application/Contracts/
 * because Procurement is the consumer; Tax owns the data and provides
 * the implementation. This is the standard "consumer-driven contracts"
 * pattern in DDD — the consumer defines the shape it needs.
 */
final class EloquentAtcCodeProvider implements AtcCodeProviderContract
{
    public function find(string $code): ?array
    {
        return Cache::remember(
            key: "tax.atc_codes.{$code}",
            ttl: 3600,
            callback: function () use ($code) {
                $row = AtcCodeModel::query()
                    ->where('code', $code)
                    ->where('is_active', true)
                    ->first();

                return $row ? [
                    'code' => $row->code,
                    'rate' => (string) $row->rate,
                    'kind' => $row->kind,
                ] : null;
            },
        );
    }
}
