<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\CompanyModel;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use App\Modules\Tax\Infrastructure\Persistence\Eloquent\Form2307ReceivedModel;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    if (! config('database.connections.pgsql.database')) {
        $this->markTestSkipped('No Postgres test database configured.');
    }
});

it('imports a multi-row CSV and returns a per-row report', function () {
    [$company, $user] = makeBulkCompanyAndUser();
    Sanctum::actingAs($user, ['*']);

    $csv = "payor_tin,payor_registered_name,payor_branch_code,payor_address,certificate_no,atc_code,period_from,period_to,income_payment,tax_withheld\n"
        ."111-111-111-000,ALPHA CORP,000,Manila,C-1,WI010,2026-01-01,2026-03-31,100000.00,5000.00\n"
        ."222-222-222-000,BETA CORP,000,Quezon,C-2,WI010,2026-01-01,2026-03-31,200000.00,10000.00\n"
        ."333-333-333-000,GAMMA CORP,000,Cebu,C-3,WI010,2026-01-01,2026-03-31,300000.00,15000.00\n";

    $response = $this->postJson('/api/v1/tax/form-2307-received/bulk-import', ['csv' => $csv]);

    $response->assertCreated();
    $response->assertJsonPath('summary.successful', 3);
    $response->assertJsonPath('summary.failed', 0);

    expect(Form2307ReceivedModel::query()
        ->where('company_id', $company->id)
        ->count())->toBe(3);
});

it('reports duplicates separately on a re-upload (idempotent)', function () {
    [$company, $user] = makeBulkCompanyAndUser();
    Sanctum::actingAs($user, ['*']);

    $csv = "payor_tin,payor_registered_name,payor_branch_code,payor_address,certificate_no,atc_code,period_from,period_to,income_payment,tax_withheld\n"
        ."111,A,000,,,WI010,2026-01-01,2026-03-31,100,5\n";

    $this->postJson('/api/v1/tax/form-2307-received/bulk-import', ['csv' => $csv])->assertCreated();
    $second = $this->postJson('/api/v1/tax/form-2307-received/bulk-import', ['csv' => $csv]);

    $second->assertStatus(422);
    $second->assertJsonPath('summary.duplicates', 1);
    $second->assertJsonPath('summary.successful', 0);
});

it('returns 400 on a CSV with the wrong header', function () {
    [$company, $user] = makeBulkCompanyAndUser();
    Sanctum::actingAs($user, ['*']);

    $csv = "tin,name\n111,A\n";
    $this->postJson('/api/v1/tax/form-2307-received/bulk-import', ['csv' => $csv])
        ->assertStatus(400)
        ->assertJsonPath('error', 'csv_format');
});

it('rejects unauthenticated callers with 401', function () {
    $this->postJson('/api/v1/tax/form-2307-received/bulk-import', ['csv' => 'x'])
        ->assertStatus(401);
});

function makeBulkCompanyAndUser(): array
{
    $company = CompanyModel::query()->create([
        'id'              => '018f0000-0000-7000-8000-000000000002',
        'tin'             => '000-321-654-000',
        'rdo_code'        => '050',
        'registered_name' => 'BULK TEST CO',
        'trade_name'      => 'BTC',
        'taxpayer_type'   => 'medium',
        'vat_status'      => 'vat',
        'address'         => '2 Test St',
        'telephone'       => null,
        'email'           => 'bulk@test.com',
        'registered_on'   => '2026-01-01',
    ]);

    $user = UserModel::query()->create([
        'id'         => '018f0000-0000-7000-8000-000000000bbb',
        'company_id' => $company->id,
        'email'      => 'bulk-admin@test.com',
        'full_name'  => 'Bulk Admin',
        'password'   => bcrypt('secret123'),
    ]);
    $user->givePermissionTo(['tax.form_2307_received.write']);

    return [$company, $user];
}
