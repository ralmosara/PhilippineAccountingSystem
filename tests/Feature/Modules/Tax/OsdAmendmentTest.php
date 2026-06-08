<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\CompanyModel;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use App\Modules\Tax\Infrastructure\Persistence\Eloquent\OsdElectionModel;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    if (! config('database.connections.pgsql.database')) {
        $this->markTestSkipped('No Postgres test database configured.');
    }
});

/**
 * Full lock → supersede → refile loop. After amendment, the previously
 * conflicting regime is now the active one and the original Q-return must
 * be regenerated to match.
 */
it('supersedes a locked OSD election and unlocks the new regime for refiling', function () {
    [$company, $user] = makeAmendCompanyAndUser();
    Sanctum::actingAs($user, ['*']);

    // Q1 locks 'itemized'
    $this->postJson('/api/v1/tax-forms/1701q/generate', [
        'year' => 2026, 'quarter' => 1, 'use_osd' => false,
    ])->assertCreated();

    $original = OsdElectionModel::query()
        ->where('company_id', $company->id)
        ->where('fiscal_year', 2026)
        ->first();
    expect($original->regime)->toBe('itemized');

    // Supersede → switch to OSD
    $resp = $this->postJson("/api/v1/tax/osd-elections/{$original->id}/supersede", [
        'new_regime' => 'osd',
        'reason'     => 'BIR letter dated 2026-09-15 approving regime shift to OSD per RR 2-2010.',
    ]);
    $resp->assertCreated();

    // Original is superseded, new active row has regime='osd'
    $original->refresh();
    expect($original->superseded_at)->not->toBeNull();

    $active = OsdElectionModel::query()
        ->where('company_id', $company->id)
        ->where('fiscal_year', 2026)
        ->whereNull('superseded_at')
        ->first();
    expect($active->regime)->toBe('osd');
    expect($active->replaces_id)->toBe($original->id);

    // Refile Q1 with the new regime — should now succeed
    $this->postJson('/api/v1/tax-forms/1701q/generate', [
        'year' => 2026, 'quarter' => 1, 'use_osd' => true,
    ])->assertCreated();
});

it('refuses to supersede with the same regime (no-op amendment)', function () {
    [$company, $user] = makeAmendCompanyAndUser();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/tax-forms/1701q/generate', [
        'year' => 2026, 'quarter' => 1, 'use_osd' => true,
    ])->assertCreated();

    $original = OsdElectionModel::query()
        ->where('company_id', $company->id)
        ->where('fiscal_year', 2026)
        ->first();

    $this->postJson("/api/v1/tax/osd-elections/{$original->id}/supersede", [
        'new_regime' => 'osd',                          // same as current
        'reason'     => 'I want to make a fix but keep the same regime please.',
    ])->assertStatus(422);
});

it('rejects supersede without MFA', function () {
    // MFA middleware coverage is tested via a separate stub that flips the
    // user's mfa_verified state. This test pins the route is MFA-gated.
    [$company, $user] = makeAmendCompanyAndUser();
    $user->mfa_verified_at = null;
    $user->save();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/tax-forms/1701q/generate', [
        'year' => 2026, 'quarter' => 1,
    ])->assertCreated();

    $original = OsdElectionModel::query()->first();

    $resp = $this->postJson("/api/v1/tax/osd-elections/{$original->id}/supersede", [
        'new_regime' => 'osd',
        'reason'     => 'placeholder reason long enough for validation.',
    ]);
    // Expect 403 from the mfa middleware (or 419 depending on impl)
    expect($resp->status())->toBeIn([403, 419, 401]);
});

function makeAmendCompanyAndUser(): array
{
    $company = CompanyModel::query()->create([
        'id'              => '018f0000-0000-7000-8000-000000000004',
        'tin'             => '000-777-888-000',
        'rdo_code'        => '050',
        'registered_name' => 'AMEND TEST CO',
        'trade_name'      => 'ATC',
        'taxpayer_type'   => 'medium',
        'vat_status'      => 'vat',
        'address'         => '4 Test St',
        'telephone'       => null,
        'email'           => 'amend@test.com',
        'registered_on'   => '2026-01-01',
    ]);

    $user = UserModel::query()->create([
        'id'                => '018f0000-0000-7000-8000-000000000ddd',
        'company_id'        => $company->id,
        'email'             => 'amend-admin@test.com',
        'full_name'         => 'Amend Admin',
        'password'          => bcrypt('secret123'),
        'mfa_verified_at'   => now(),
    ]);
    $user->givePermissionTo([
        'tax.forms.generate',
        'tax.osd_election.view',
        'tax.osd_election.supersede',
    ]);

    return [$company, $user];
}
