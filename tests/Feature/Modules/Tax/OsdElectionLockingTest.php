<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\CompanyModel;
use App\Modules\Tax\Infrastructure\Persistence\Eloquent\OsdElectionModel;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(TestCase::class);

/**
 * End-to-end exercise of the OSD lock-in flow. Requires a running test
 * Postgres (config('database.connections.pgsql_test')) — Pest skips if absent.
 *
 * Storyline:
 *   1. Auth user with 'tax.forms.generate' permission
 *   2. POST /tax-forms/1701q/generate { year:2026, quarter:1, use_osd:true }
 *      → 201; tax.osd_elections has one row, regime='osd'
 *   3. POST /tax-forms/1701q/generate { year:2026, quarter:2, use_osd:false }
 *      → 422 (or whatever maps OsdElectionMismatchException); election unchanged
 *   4. POST /tax-forms/1701q/generate { year:2026, quarter:2, use_osd:true }
 *      → 201; election still one active row
 */

beforeEach(function () {
    // Skip the entire file if no test DB is configured — this matches CI's
    // behaviour where Postgres is provisioned only on the feature-test stage.
    if (! config('database.connections.pgsql.database')) {
        $this->markTestSkipped('No Postgres test database configured.');
    }
});

it('records an OSD election on the first 1701Q generation', function () {
    [$company, $user] = makeCompanyAndUser();
    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/api/v1/tax-forms/1701q/generate', [
        'year'    => 2026,
        'quarter' => 1,
        'use_osd' => true,
    ]);

    $response->assertCreated();

    $election = OsdElectionModel::query()
        ->where('company_id', $company->id)
        ->where('fiscal_year', 2026)
        ->where('taxpayer_type', 'individual')
        ->first();

    expect($election)->not->toBeNull();
    expect($election->regime)->toBe('osd');
    expect($election->superseded_at)->toBeNull();
});

it('refuses a second Q-return that contradicts Q1\'s regime', function () {
    [$company, $user] = makeCompanyAndUser();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/tax-forms/1701q/generate', [
        'year' => 2026, 'quarter' => 1, 'use_osd' => true,
    ])->assertCreated();

    $this->postJson('/api/v1/tax-forms/1701q/generate', [
        'year' => 2026, 'quarter' => 2, 'use_osd' => false,
    ])->assertStatus(422);

    // Election unchanged — still 'osd', still single active row
    $rows = OsdElectionModel::query()
        ->where('company_id', $company->id)
        ->where('fiscal_year', 2026)
        ->get();

    expect($rows)->toHaveCount(1);
    expect($rows->first()->regime)->toBe('osd');
});

it('allows a matching second Q-return (idempotent confirmation)', function () {
    [$company, $user] = makeCompanyAndUser();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/tax-forms/1701q/generate', [
        'year' => 2026, 'quarter' => 1, 'use_osd' => true,
    ])->assertCreated();

    $this->postJson('/api/v1/tax-forms/1701q/generate', [
        'year' => 2026, 'quarter' => 2, 'use_osd' => true,
    ])->assertCreated();

    expect(OsdElectionModel::query()
        ->where('company_id', $company->id)
        ->where('fiscal_year', 2026)
        ->count())->toBe(1);
});

function makeCompanyAndUser(): array
{
    $company = CompanyModel::query()->create([
        'id'              => '018f0000-0000-7000-8000-000000000001',
        'tin'             => '000-123-456-000',
        'rdo_code'        => '039',
        'registered_name' => 'TEST CO',
        'trade_name'      => 'TEST',
        'taxpayer_type'   => 'medium',
        'vat_status'      => 'vat',
        'address'         => '1 Test St',
        'telephone'       => null,
        'email'           => 'test@example.com',
        'registered_on'   => '2026-01-01',
    ]);

    $user = UserModel::query()->create([
        'id'         => '018f0000-0000-7000-8000-000000000aaa',
        'company_id' => $company->id,
        'email'      => 'admin@test.com',
        'full_name'  => 'Test Admin',
        'password'   => bcrypt('secret123'),
    ]);
    $user->givePermissionTo(['tax.forms.generate']);

    return [$company, $user];
}
