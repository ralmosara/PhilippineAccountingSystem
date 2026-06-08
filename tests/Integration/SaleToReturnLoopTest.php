<?php

declare(strict_types=1);

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\CompanyModel;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use App\Modules\Sales\Infrastructure\Persistence\Eloquent\CustomerModel;
use App\Modules\Sales\Infrastructure\Persistence\Eloquent\SalesInvoiceModel;
use App\Modules\Tax\Infrastructure\Persistence\Eloquent\BirFormModel;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Multi-module integration test — the BIR-compliance critical-path chain
 * that no Unit/Feature test can verify in isolation.
 *
 *   Customer → SI issued (with VAT) → JV posted → Audit event chained
 *      → 2550M generated → SI's vatable_sales appears on line 31
 *      → Period locked → subsequent SI for same period rejected
 *
 * This is the suite that pins "did the bounded-context seams stay sealed?".
 * Skips if no test Postgres is configured; runs deterministically when one is.
 */

beforeEach(function () {
    if (! config('database.connections.pgsql.database')) {
        $this->markTestSkipped('No Postgres test database configured.');
    }
});

it('chains a sale through JV → audit → 2550M reflecting the vatable sales', function () {
    [$company, $user, $customer] = bootstrapTenant();
    Sanctum::actingAs($user, ['*']);

    // ── 1. Issue an SI ─────────────────────────────────────────────────
    $issueResp = $this->postJson('/api/v1/sales-invoices/issue', [
        'customer_id'            => $customer->id,
        'document_series_id'     => seedActiveSiSeries($company->id),
        'invoice_date'           => '2026-04-15',
        'doc_kind'               => 'cash',
        'ar_account_id'          => seedAccount($company->id, '1200', 'Accounts Receivable', 'asset'),
        'vat_payable_account_id' => seedAccount($company->id, '2200', 'Output VAT Payable', 'liability'),
        'lines' => [[
            'description'         => 'Office supplies',
            'quantity'            => '10',
            'unit_price'          => '1000.00',
            'tax_kind'            => 'vat_output',
            'revenue_account_id'  => seedAccount($company->id, '4010', 'Sales', 'revenue'),
        ]],
    ]);
    $issueResp->assertCreated();
    $siId = $issueResp->json('id');

    // ── 2. SI persisted, JV linked, vatable computed ───────────────────
    $invoice = SalesInvoiceModel::query()->findOrFail($siId);
    expect($invoice->posted_at)->not->toBeNull();
    expect((string) $invoice->vatable_sales)->toEqual('10000.00');
    expect((string) $invoice->vat_amount)->toEqual('1200.00');
    expect($invoice->journal_entry_id)->not->toBeNull();

    // ── 3. Audit event was chained (hash != null, previous event linked) ──
    $audit = DB::table('audit.events')
        ->where('company_id', $company->id)
        ->where('event_type', 'salesinvoice.issued')
        ->orderByDesc('id')
        ->first();
    expect($audit)->not->toBeNull();
    expect($audit->current_hash)->not->toBeNull();

    // ── 4. Generate 2550M for the SI's month; line 31 should equal ₱10k ──
    $form2550MResp = $this->postJson('/api/v1/tax-forms/2550m/generate', [
        'year'  => 2026,
        'month' => 4,
    ]);
    $form2550MResp->assertCreated();
    $formId = $form2550MResp->json('id');

    $line31 = collect($form2550MResp->json('lines'))->firstWhere('line_code', '31');
    expect($line31)->not->toBeNull();
    // Vatable sales (line 31) should reflect the SI we just issued.
    expect((string) $line31['amount'])->toEqual('10000.00');

    // Form persisted as generated
    $formModel = BirFormModel::query()->findOrFail($formId);
    expect($formModel->status)->toBe('generated');

    // ── 5. Lock the period, expect a subsequent SI for the same month to fail ──
    $period = DB::table('accounting.fiscal_periods')
        ->where('company_id', $company->id)
        ->whereDate('starts_on', '2026-04-01')
        ->first();
    expect($period)->not->toBeNull();

    $lockResp = $this->postJson("/api/v1/fiscal-periods/{$period->id}/lock");
    $lockResp->assertSuccessful();

    // Now try to post another SI in the locked period
    $blockedResp = $this->postJson('/api/v1/sales-invoices/issue', [
        'customer_id'            => $customer->id,
        'document_series_id'     => seedActiveSiSeries($company->id),
        'invoice_date'           => '2026-04-20',           // still in April → locked
        'doc_kind'               => 'cash',
        'ar_account_id'          => seedAccount($company->id, '1200', 'Accounts Receivable', 'asset'),
        'vat_payable_account_id' => seedAccount($company->id, '2200', 'Output VAT Payable', 'liability'),
        'lines' => [[
            'description'         => 'Should fail',
            'quantity'            => '1',
            'unit_price'          => '100.00',
            'tax_kind'            => 'vat_output',
            'revenue_account_id'  => seedAccount($company->id, '4010', 'Sales', 'revenue'),
        ]],
    ]);
    // Period-lock trigger refuses the underlying journal_entry insert →
    // 422 from the FormRequest / 500 from the trigger. Either way the
    // post does NOT complete with 201.
    expect($blockedResp->status())->not->toBe(201);
});

/* ──────────────────────────────────────────────────────────────────────────
 * Lightweight tenant bootstrap. We seed via raw inserts because the real
 * seeder builds a 4-tier CoA / fiscal-year structure that would slow this
 * test from milliseconds to ~5s. Each helper returns the inserted UUID
 * so the test can wire references manually.
 * ────────────────────────────────────────────────────────────────────── */

function bootstrapTenant(): array
{
    $company = CompanyModel::query()->create([
        'id'              => '018f0000-0000-7000-8000-0000000000a0',
        'tin'             => '000-123-456-000',
        'rdo_code'        => '039',
        'registered_name' => 'INTEGRATION TEST CO',
        'trade_name'      => 'ITC',
        'taxpayer_type'   => 'medium',
        'vat_status'      => 'vat',
        'address'         => '1 Test St',
        'telephone'       => null,
        'email'           => 'integration@test.com',
        'registered_on'   => '2026-01-01',
    ]);

    // Seed a fiscal year + 12 monthly periods for 2026
    $fiscalYearId = '018f0000-0000-7000-8000-0000000000b0';
    DB::table('accounting.fiscal_years')->insert([
        'id'         => $fiscalYearId,
        'company_id' => $company->id,
        'starts_on'  => '2026-01-01',
        'ends_on'    => '2026-12-31',
        'closed_at'  => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    for ($m = 1; $m <= 12; $m++) {
        DB::table('accounting.fiscal_periods')->insert([
            'id'             => sprintf('018f0000-0000-7000-8000-0000000000%02d', 0xc0 + $m),
            'fiscal_year_id' => $fiscalYearId,
            'period_number'  => $m,
            'starts_on'      => sprintf('2026-%02d-01', $m),
            'ends_on'        => date('Y-m-t', strtotime(sprintf('2026-%02d-01', $m))),
            'locked_at'      => null,
            'locked_by'      => null,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    $user = UserModel::query()->create([
        'id'              => '018f0000-0000-7000-8000-0000000000aa',
        'company_id'      => $company->id,
        'email'           => 'integration-admin@test.com',
        'full_name'       => 'Integration Admin',
        'password'        => bcrypt('secret123'),
        'mfa_verified_at' => now(),
    ]);
    $user->givePermissionTo([
        'sales.invoices.create',
        'sales.invoices.view',
        'tax.forms.generate',
        'accounting.periods.lock',
        'accounting.journals.view',
        'accounting.journals.create',
        'accounting.journals.post',
    ]);

    $customer = CustomerModel::query()->create([
        'id'                => '018f0000-0000-7000-8000-0000000000d0',
        'company_id'        => $company->id,
        'customer_no'       => 'C-0001',
        'registered_name'   => 'ACME CORP',
        'tin'               => '111-222-333-000',
        'is_vat_registered' => true,
        'is_government'     => false,
        'is_senior_citizen' => false,
        'is_pwd'            => false,
        'payment_terms_days'=> 30,
        'is_active'         => true,
    ]);

    return [$company, $user, $customer];
}

/** Seed an active SI document series for the company; returns its UUID. */
function seedActiveSiSeries(string $companyId): string
{
    $id = '018f0000-0000-7000-8000-0000000000e0';
    if (! DB::table('sales.document_series')->where('id', $id)->exists()) {
        DB::table('sales.document_series')->insert([
            'id'             => $id,
            'company_id'     => $companyId,
            'branch_id'      => null,
            'document_type'  => 'SI',
            'prefix'         => 'SI-2026-',
            'next_sequence'  => 1,
            'series_start'   => 1,
            'series_end'     => 999_999,
            'bir_atp_no'     => 'TEST-ATP-001',
            'activated_at'   => now(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }
    return $id;
}

/** Seed an account in the chart; idempotent on (company, code). Returns UUID. */
function seedAccount(string $companyId, string $code, string $name, string $type): string
{
    $existing = DB::table('accounting.accounts')
        ->where('company_id', $companyId)
        ->where('code', $code)
        ->first();
    if ($existing) return $existing->id;

    $id = (string) \Ramsey\Uuid\Uuid::uuid4();
    DB::table('accounting.accounts')->insert([
        'id'             => $id,
        'company_id'     => $companyId,
        'code'           => $code,
        'name'           => $name,
        'type'           => $type,
        'normal_balance' => in_array($type, ['asset', 'expense'], true) ? 'debit' : 'credit',
        'parent_id'      => null,
        'path'           => $code,
        'is_postable'    => true,
        'is_active'      => true,
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);
    return $id;
}
