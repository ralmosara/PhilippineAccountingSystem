<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * Seeds inventory bootstrap data:
 *   - 10 common Units of Measure (global)
 *   - Demo company's default warehouse (head office)
 *   - 5 item categories
 *   - 8 demo items spanning service / stock / FG so cross-module pipelines have data
 */
final class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $this->seedUnitsOfMeasure();

        if (app()->environment('production')) {
            $this->command?->warn('InventorySeeder: skipping company-scoped seed in production.');
            return;
        }

        $company = DB::table('identity.companies')
            ->where('registered_name', 'ABC Trading Incorporated')
            ->first();
        if (! $company) {
            return;
        }

        $branch = DB::table('identity.branches')->where('company_id', $company->id)->first();

        $this->seedWarehouses($company->id, $branch->id ?? null);
        $categories = $this->seedCategories($company->id);
        $this->seedItems($company->id, $categories);
    }

    private function seedUnitsOfMeasure(): void
    {
        $now = now();
        $uoms = [
            ['code' => 'pc',   'name' => 'Piece',     'category' => 'count'],
            ['code' => 'pcs',  'name' => 'Pieces',    'category' => 'count'],
            ['code' => 'box',  'name' => 'Box',       'category' => 'count'],
            ['code' => 'kg',   'name' => 'Kilogram',  'category' => 'weight'],
            ['code' => 'g',    'name' => 'Gram',      'category' => 'weight'],
            ['code' => 'L',    'name' => 'Liter',     'category' => 'volume'],
            ['code' => 'mL',   'name' => 'Milliliter','category' => 'volume'],
            ['code' => 'm',    'name' => 'Meter',     'category' => 'length'],
            ['code' => 'hr',   'name' => 'Hour',      'category' => 'time'],
            ['code' => 'svc',  'name' => 'Service',   'category' => 'count'],
        ];

        $rows = array_map(fn ($u) => [
            'id'         => Uuid::uuid5(Uuid::NAMESPACE_OID, 'pha.uom:'.$u['code'])->toString(),
            'code'       => $u['code'],
            'name'       => $u['name'],
            'category'   => $u['category'],
            'is_active'  => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $uoms);

        DB::table('inventory.units_of_measure')->upsert($rows, ['code'], ['name', 'category', 'updated_at']);

        $this->command?->info(sprintf('Seeded %d units of measure.', count($rows)));
    }

    private function seedWarehouses(string $companyId, ?string $branchId): void
    {
        $now = now();
        DB::table('inventory.warehouses')->upsert(
            [[
                'id'         => Uuid::uuid5(Uuid::NAMESPACE_OID, "pha.warehouse:{$companyId}:HO")->toString(),
                'company_id' => $companyId,
                'branch_id'  => $branchId,
                'code'       => 'HO',
                'name'       => 'Head Office Warehouse',
                'address'    => '123 Ayala Avenue, Makati City',
                'is_default' => true,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['company_id', 'code'],
            ['name', 'address', 'is_default', 'updated_at'],
        );

        $this->command?->info('Seeded default warehouse.');
    }

    /** @return array<string, string> code → uuid */
    private function seedCategories(string $companyId): array
    {
        $now = now();
        $cats = [
            ['code' => 'OFFICE',   'name' => 'Office Supplies'],
            ['code' => 'IT',       'name' => 'IT Equipment'],
            ['code' => 'STATION', 'name' => 'Stationery'],
            ['code' => 'CLEANING','name' => 'Cleaning Supplies'],
            ['code' => 'SERVICE',  'name' => 'Services'],
        ];

        $ids = [];
        foreach ($cats as $c) {
            $id = Uuid::uuid5(Uuid::NAMESPACE_OID, "pha.cat:{$companyId}:{$c['code']}")->toString();
            $ids[$c['code']] = $id;

            DB::table('inventory.item_categories')->upsert(
                [[
                    'id'         => $id,
                    'company_id' => $companyId,
                    'code'       => $c['code'],
                    'name'       => $c['name'],
                    'path'       => strtolower($c['code']),
                    'is_active'  => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]],
                ['company_id', 'code'],
                ['name', 'path', 'updated_at'],
            );
        }

        $this->command?->info(sprintf('Seeded %d item categories.', count($cats)));
        return $ids;
    }

    /** @param array<string, string> $categoryIds */
    private function seedItems(string $companyId, array $categoryIds): void
    {
        $uomPc  = DB::table('inventory.units_of_measure')->where('code', 'pc')->value('id');
        $uomBox = DB::table('inventory.units_of_measure')->where('code', 'box')->value('id');
        $uomHr  = DB::table('inventory.units_of_measure')->where('code', 'hr')->value('id');
        $uomSvc = DB::table('inventory.units_of_measure')->where('code', 'svc')->value('id');

        $now = now();
        $items = [
            ['sku' => 'PEN-001',   'name' => 'Ballpoint Pen, Blue',     'cat' => 'STATION',  'uom' => $uomPc,  'price' => '15.00',    'cost' => '8.00',    'kind' => 'stock'],
            ['sku' => 'PAPER-A4',  'name' => 'Bond Paper A4 (500 sheets)','cat' => 'OFFICE', 'uom' => $uomBox, 'price' => '250.00',   'cost' => '180.00',  'kind' => 'stock'],
            ['sku' => 'STAPLER-01','name' => 'Stapler, Heavy Duty',     'cat' => 'OFFICE',   'uom' => $uomPc,  'price' => '450.00',   'cost' => '280.00',  'kind' => 'stock'],
            ['sku' => 'LAPTOP-DEV','name' => 'Developer Laptop (i7/16GB/512GB)', 'cat' => 'IT', 'uom' => $uomPc, 'price' => '75000.00','cost' => '55000.00','kind' => 'stock'],
            ['sku' => 'MOUSE-WL',  'name' => 'Wireless Mouse',          'cat' => 'IT',       'uom' => $uomPc,  'price' => '850.00',   'cost' => '500.00',  'kind' => 'stock'],
            ['sku' => 'CLEAN-MOP', 'name' => 'Floor Mop',               'cat' => 'CLEANING', 'uom' => $uomPc,  'price' => '350.00',   'cost' => '200.00',  'kind' => 'stock'],
            ['sku' => 'CONSULT-HR','name' => 'Consulting Service (per hour)', 'cat' => 'SERVICE', 'uom' => $uomHr, 'price' => '2500.00','cost' => '0',  'kind' => 'service', 'inventory' => false],
            ['sku' => 'SUPPORT-PK','name' => 'Annual Support Package',  'cat' => 'SERVICE',  'uom' => $uomSvc, 'price' => '120000.00','cost' => '0',  'kind' => 'service', 'inventory' => false],
        ];

        $rows = [];
        foreach ($items as $item) {
            $rows[] = [
                'id'              => Uuid::uuid5(Uuid::NAMESPACE_OID, "pha.item:{$companyId}:{$item['sku']}")->toString(),
                'company_id'      => $companyId,
                'category_id'     => $categoryIds[$item['cat']],
                'sku'             => $item['sku'],
                'name'            => $item['name'],
                'kind'            => $item['kind'],
                'uom_id'          => $item['uom'],
                'costing_method'  => 'moving_average',
                'moving_avg_cost' => $item['cost'],
                'selling_price'   => $item['price'],
                'is_vatable'      => true,
                'is_inventory'    => $item['inventory'] ?? true,
                'is_active'       => true,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        DB::table('inventory.items')->upsert(
            $rows,
            ['company_id', 'sku'],
            ['name', 'kind', 'uom_id', 'moving_avg_cost', 'selling_price', 'updated_at'],
        );

        $this->command?->info(sprintf('Seeded %d demo items.', count($rows)));
    }
}
