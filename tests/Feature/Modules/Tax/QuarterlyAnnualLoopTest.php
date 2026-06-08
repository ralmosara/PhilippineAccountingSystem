<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\CompanyModel;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use App\Modules\Tax\Infrastructure\Persistence\Eloquent\BirFormModel;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    if (! config('database.connections.pgsql.database')) {
        $this->markTestSkipped('No Postgres test database configured.');
    }
});

/**
 * Pins the closed loop between quarterly and annual ITRs:
 *
 *   Q1 1702Q → tax_paid = X1
 *   Q2 1702Q → tax_paid = X2 (cumulative tax due − prior Q1 X1)
 *   Q3 1702Q → tax_paid = X3
 *   Annual 1702-RT (with quarterly_payments unspecified)
 *     → auto-sums X1 + X2 + X3 via priorQuarterTaxPayments
 *
 * This test confirms the annual really pulls from prior quarters when the
 * user leaves the credit field at 0.00.
 */

it('annual 1702-RT auto-sums prior 1702Q payments when no override supplied', function () {
    [$company, $user] = makeLoopCompanyAndUser();
    Sanctum::actingAs($user, ['*']);

    // Generate Q1/Q2/Q3 (cumulative figures — JEs would be seeded in a real
    // test; here we trust the generator to compute zeros for an empty ledger).
    foreach ([1, 2, 3] as $q) {
        $this->postJson('/api/v1/tax-forms/1702q/generate', [
            'year' => 2026, 'quarter' => $q,
        ])->assertCreated();
    }

    // Force a known tax_paid on each Q so the annual has something to sum.
    BirFormModel::query()
        ->where('company_id', $company->id)
        ->where('form_type', '1702Q')
        ->where('year', 2026)
        ->where('quarter', 1)->update(['tax_paid' => '100000.00', 'status' => 'filed']);
    BirFormModel::query()
        ->where('company_id', $company->id)
        ->where('form_type', '1702Q')
        ->where('year', 2026)
        ->where('quarter', 2)->update(['tax_paid' => '150000.00', 'status' => 'filed']);
    BirFormModel::query()
        ->where('company_id', $company->id)
        ->where('form_type', '1702Q')
        ->where('year', 2026)
        ->where('quarter', 3)->update(['tax_paid' => '200000.00', 'status' => 'filed']);

    // Now generate the annual — without an explicit quarterly_payments, it
    // should pull 100k + 150k + 200k = 450k.
    $annual = $this->postJson('/api/v1/tax-forms/1702rt/generate', [
        'year' => 2026,
    ]);
    $annual->assertCreated();

    // Inspect the "Quarterly ITR Payments Made" line on the response
    $line22A = collect($annual->json('lines'))->firstWhere('line_code', '22A');
    expect($line22A['amount'])->toEqual('450000.00');
});

function makeLoopCompanyAndUser(): array
{
    $company = CompanyModel::query()->create([
        'id'              => '018f0000-0000-7000-8000-000000000003',
        'tin'             => '000-555-666-000',
        'rdo_code'        => '050',
        'registered_name' => 'LOOP TEST CORP',
        'trade_name'      => 'LTC',
        'taxpayer_type'   => 'medium',
        'vat_status'      => 'vat',
        'address'         => '3 Test St',
        'telephone'       => null,
        'email'           => 'loop@test.com',
        'registered_on'   => '2026-01-01',
    ]);

    $user = UserModel::query()->create([
        'id'         => '018f0000-0000-7000-8000-000000000ccc',
        'company_id' => $company->id,
        'email'      => 'loop-admin@test.com',
        'full_name'  => 'Loop Admin',
        'password'   => bcrypt('secret123'),
    ]);
    $user->givePermissionTo(['tax.forms.generate']);

    return [$company, $user];
}
