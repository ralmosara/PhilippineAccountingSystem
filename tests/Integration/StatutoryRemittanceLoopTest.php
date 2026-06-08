<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\CompanyModel;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Statutory remittance loop — the SSS / PhilHealth / Pag-IBIG analog of
 * SaleToReturnLoopTest. Exercises the full stack:
 *
 *   approved PayrollRun
 *      ─► EloquentStatutoryRemittanceAggregator pulls payslip lines
 *      ─► StatutoryRemittanceFormatter produces agency-specific CSV
 *      ─► GenerateStatutoryRemittance persists to MinIO + audits
 *      ─► HTTP controller returns the body for download
 *
 * Skips automatically if the test Postgres is not provisioned. Faked MinIO
 * is replaced by the in-memory storage so the test never touches network.
 */

beforeEach(function () {
    if (! config('database.connections.pgsql.database')) {
        $this->markTestSkipped('No Postgres test database configured.');
    }
    Storage::fake('minio');
});

it('SSS R-3 endpoint returns a CSV body summing approved payroll runs', function () {
    [$company, $user] = bootstrapPayrollTenant();
    Sanctum::actingAs($user, ['*']);
    seedApprovedAprilPayroll($company->id);

    $resp = $this->postJson('/api/v1/payroll/remittances/sss-r3/generate', [
        'period_from' => '2026-04-01',
        'period_to'   => '2026-04-30',
    ]);

    $resp->assertCreated();
    $resp->assertJsonStructure([
        'id', 'agency', 'form_code', 'period_from', 'period_to',
        'line_count', 'total_remittance', 'storage_path', 'file_body',
    ]);

    expect($resp->json('agency'))->toBe('sss')
        ->and($resp->json('form_code'))->toBe('R-3')
        ->and($resp->json('line_count'))->toBe(2);

    // File body is CSV; header row uses commas + CRLF
    $body = $resp->json('file_body');
    expect($body)->toContain("\r\n")
        ->and($body)->toStartWith('H,')
        ->and(substr_count($body, "\r\n"))->toBeGreaterThanOrEqual(3);   // header + 2 detail

    // Persisted to MinIO under the agency-namespaced path
    Storage::disk('minio')->assertExists($resp->json('storage_path'));
});

it('PhilHealth RF-1 endpoint uses pipe delimiters and YYYYMM period', function () {
    [$company, $user] = bootstrapPayrollTenant();
    Sanctum::actingAs($user, ['*']);
    seedApprovedAprilPayroll($company->id);

    $resp = $this->postJson('/api/v1/payroll/remittances/philhealth-rf1/generate', [
        'period_from' => '2026-04-01',
        'period_to'   => '2026-04-30',
    ]);

    $resp->assertCreated();
    expect($resp->json('agency'))->toBe('philhealth')
        ->and($resp->json('form_code'))->toBe('RF-1');

    $body = $resp->json('file_body');
    expect($body)->toContain('|202604|')           // YYYYMM
        ->and($body)->toContain('|')                // pipe delimiter
        ->and($body)->not->toContain("\nH,");       // not the SSS comma format
});

it('Pag-IBIG MCRF endpoint uses MM/YYYY applicable period', function () {
    [$company, $user] = bootstrapPayrollTenant();
    Sanctum::actingAs($user, ['*']);
    seedApprovedAprilPayroll($company->id);

    $resp = $this->postJson('/api/v1/payroll/remittances/pagibig-mcrf/generate', [
        'period_from' => '2026-04-01',
        'period_to'   => '2026-04-30',
    ]);

    $resp->assertCreated();
    expect($resp->json('agency'))->toBe('pagibig')
        ->and($resp->json('form_code'))->toBe('MCRF');

    expect($resp->json('file_body'))->toContain(',04/2026,');   // MM/YYYY
});

it('excludes draft + computed payroll runs (only "approved" rolls into remittance)', function () {
    [$company, $user] = bootstrapPayrollTenant();
    Sanctum::actingAs($user, ['*']);

    // Seed one APPROVED + one COMPUTED-but-not-approved run, same period.
    seedApprovedAprilPayroll($company->id);
    seedComputedAprilPayrollNotApproved($company->id);

    $resp = $this->postJson('/api/v1/payroll/remittances/sss-r3/generate', [
        'period_from' => '2026-04-01',
        'period_to'   => '2026-04-30',
    ]);

    $resp->assertCreated();
    // The 2 employees from the APPROVED run only; the computed-but-not-approved
    // run's 1 extra employee must not appear.
    expect($resp->json('line_count'))->toBe(2);
});

it('writes an audit event with the full remittance summary', function () {
    [$company, $user] = bootstrapPayrollTenant();
    Sanctum::actingAs($user, ['*']);
    seedApprovedAprilPayroll($company->id);

    $this->postJson('/api/v1/payroll/remittances/sss-r3/generate', [
        'period_from' => '2026-04-01',
        'period_to'   => '2026-04-30',
    ])->assertCreated();

    $audit = DB::table('audit.events')
        ->where('company_id', $company->id)
        ->where('event_type', 'statutoryremittance.generated')
        ->orderByDesc('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and(json_decode($audit->payload, true))->toMatchArray([
            'agency'    => 'sss',
            'form_code' => 'R-3',
            'line_count' => 2,
        ]);
});

it('refuses generation when the user lacks payroll.statutory.file permission', function () {
    [$company] = bootstrapPayrollTenant();

    // Build a user with NO permissions, log in
    $unprivileged = UserModel::query()->create([
        'id'         => '018f0000-0000-7000-8000-0000000000ef',
        'company_id' => $company->id,
        'email'      => 'no-perms@test.com',
        'full_name'  => 'Limited User',
        'password'   => bcrypt('secret123'),
    ]);
    Sanctum::actingAs($unprivileged, ['*']);

    $resp = $this->postJson('/api/v1/payroll/remittances/sss-r3/generate', [
        'period_from' => '2026-04-01',
        'period_to'   => '2026-04-30',
    ]);

    expect($resp->status())->toBeIn([403, 401]);
});

/* ── Fixtures ─────────────────────────────────────────────────────────── */

function bootstrapPayrollTenant(): array
{
    $company = CompanyModel::query()->create([
        'id'              => '018f0000-0000-7000-8000-000000000300',
        'tin'             => '000-321-654-000',
        'rdo_code'        => '050',
        'registered_name' => 'PAYROLL TEST CO',
        'trade_name'      => 'PTC',
        'taxpayer_type'   => 'medium',
        'vat_status'      => 'vat',
        'address'         => '5 Test St',
        'telephone'       => null,
        'email'           => 'payroll@test.com',
        'registered_on'   => '2026-01-01',
    ]);

    $user = UserModel::query()->create([
        'id'              => '018f0000-0000-7000-8000-000000000301',
        'company_id'      => $company->id,
        'email'           => 'payroll-admin@test.com',
        'full_name'       => 'Payroll Admin',
        'password'        => bcrypt('secret123'),
        'mfa_verified_at' => now(),
    ]);
    $user->givePermissionTo([
        'payroll.runs.view', 'payroll.runs.compute', 'payroll.runs.approve',
        'payroll.statutory.file',
    ]);

    return [$company, $user];
}

/**
 * Seeds an APPROVED April-2026 payroll run with 2 employees, each with
 * concrete SSS/PhilHealth/Pag-IBIG contributions. Keeps the fixture
 * minimal: no fiscal-year/period dependencies, raw inserts only.
 */
function seedApprovedAprilPayroll(string $companyId): void
{
    $runId = '018f0000-0000-7000-8000-000000000310';
    $emp1  = '018f0000-0000-7000-8000-000000000311';
    $emp2  = '018f0000-0000-7000-8000-000000000312';

    DB::table('hr.employees')->insert([
        [
            'id'             => $emp1,
            'company_id'     => $companyId,
            'employee_no'    => 'E-001',
            'full_name'      => 'GARCIA, ROBERTO',
            'tin'            => '111-222-333-000',
            'sss_no'         => '34-1234567-8',
            'philhealth_no'  => '11-222333444-5',
            'pagibig_no'     => '1234-5678-9012',
            'hired_on'       => '2024-01-01',
            'is_active'      => true,
            'created_at'     => now(),
            'updated_at'     => now(),
        ],
        [
            'id'             => $emp2,
            'company_id'     => $companyId,
            'employee_no'    => 'E-002',
            'full_name'      => 'CRUZ, MARIA',
            'tin'            => '222-333-444-000',
            'sss_no'         => '34-2345678-9',
            'philhealth_no'  => '11-333444555-6',
            'pagibig_no'     => '2345-6789-0123',
            'hired_on'       => '2024-01-01',
            'is_active'      => true,
            'created_at'     => now(),
            'updated_at'     => now(),
        ],
    ]);

    DB::table('payroll.payroll_runs')->insert([
        'id'           => $runId,
        'company_id'   => $companyId,
        'period_start' => '2026-04-01',
        'period_end'   => '2026-04-30',
        'status'       => 'approved',
        'computed_at'  => '2026-04-30 18:00:00',
        'approved_at'  => '2026-05-01 09:00:00',
        'approved_by'  => '018f0000-0000-7000-8000-000000000301',
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    DB::table('payroll.payslip_lines')->insert([
        [
            'id'                => '018f0000-0000-7000-8000-000000000321',
            'payroll_run_id'    => $runId,
            'employee_id'       => $emp1,
            'gross'             => '30000.00',
            'sss_ee'            => '900.00',
            'sss_er'            => '1820.00',
            'sss_ec'            => '30.00',
            'phic_ee'           => '600.00',
            'phic_er'           => '600.00',
            'hdmf_ee'           => '200.00',
            'hdmf_er'           => '200.00',
            'withholding_tax'   => '2500.00',
            'net_pay'           => '25800.00',
            'created_at'        => now(),
            'updated_at'        => now(),
        ],
        [
            'id'                => '018f0000-0000-7000-8000-000000000322',
            'payroll_run_id'    => $runId,
            'employee_id'       => $emp2,
            'gross'             => '25000.00',
            'sss_ee'            => '750.00',
            'sss_er'            => '1530.00',
            'sss_ec'            => '30.00',
            'phic_ee'           => '500.00',
            'phic_er'           => '500.00',
            'hdmf_ee'           => '200.00',
            'hdmf_er'           => '200.00',
            'withholding_tax'   => '1800.00',
            'net_pay'           => '21750.00',
            'created_at'        => now(),
            'updated_at'        => now(),
        ],
    ]);
}

function seedComputedAprilPayrollNotApproved(string $companyId): void
{
    $runId = '018f0000-0000-7000-8000-000000000330';
    $emp3  = '018f0000-0000-7000-8000-000000000331';

    DB::table('hr.employees')->insert([
        'id'             => $emp3,
        'company_id'     => $companyId,
        'employee_no'    => 'E-003',
        'full_name'      => 'SANTOS, ANNA',
        'tin'            => '333-444-555-000',
        'sss_no'         => '34-3456789-0',
        'philhealth_no'  => '11-444555666-7',
        'pagibig_no'     => '3456-7890-1234',
        'hired_on'       => '2024-01-01',
        'is_active'      => true,
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    DB::table('payroll.payroll_runs')->insert([
        'id'           => $runId,
        'company_id'   => $companyId,
        'period_start' => '2026-04-01',
        'period_end'   => '2026-04-30',
        'status'       => 'computed',          // NOT approved
        'computed_at'  => '2026-04-30 18:00:00',
        'approved_at'  => null,
        'approved_by'  => null,
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    DB::table('payroll.payslip_lines')->insert([
        'id'                => '018f0000-0000-7000-8000-000000000341',
        'payroll_run_id'    => $runId,
        'employee_id'       => $emp3,
        'gross'             => '20000.00',
        'sss_ee'            => '600.00',
        'sss_er'            => '1230.00',
        'sss_ec'            => '30.00',
        'phic_ee'           => '400.00',
        'phic_er'           => '400.00',
        'hdmf_ee'           => '200.00',
        'hdmf_er'           => '200.00',
        'withholding_tax'   => '1200.00',
        'net_pay'           => '17400.00',
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);
}
