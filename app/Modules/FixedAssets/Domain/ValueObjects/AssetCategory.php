<?php

declare(strict_types=1);

namespace App\Modules\FixedAssets\Domain\ValueObjects;

/**
 * AssetCategory — backed enum for all supported fixed-asset categories.
 *
 * defaultUsefulLifeMonths() returns the PFRS-for-SMEs recommended useful life
 * used to pre-fill the register form and validate at registration.
 * Land = 0 because it never depreciates.
 */
enum AssetCategory: string
{
    case Land                  = 'land';
    case Building              = 'building';
    case Equipment             = 'equipment';
    case Vehicle               = 'vehicle';
    case Furniture             = 'furniture';
    case ItEquipment           = 'it_equipment';
    case LeaseholdImprovement  = 'leasehold_improvement';

    /**
     * Default useful life in months per asset category.
     * Land is 0 — it is never depreciated.
     */
    public function defaultUsefulLifeMonths(): int
    {
        return match ($this) {
            self::Land                 => 0,
            self::Building             => 480,   // 40 years
            self::Equipment            => 60,    // 5 years
            self::Vehicle              => 60,    // 5 years
            self::Furniture            => 60,    // 5 years
            self::ItEquipment          => 36,    // 3 years
            self::LeaseholdImprovement => 120,   // 10 years
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Land                 => 'Land',
            self::Building             => 'Building',
            self::Equipment            => 'Equipment',
            self::Vehicle              => 'Vehicle',
            self::Furniture            => 'Furniture & Fixtures',
            self::ItEquipment          => 'IT Equipment',
            self::LeaseholdImprovement => 'Leasehold Improvement',
        };
    }
}
