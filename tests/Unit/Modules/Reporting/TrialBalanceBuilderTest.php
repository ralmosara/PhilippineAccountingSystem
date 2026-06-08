<?php

declare(strict_types=1);

use App\Modules\Reporting\Domain\Services\TrialBalanceBuilder;
use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;

/**
 * Trial Balance is the first verification artifact emitted in any audit:
 * every entry that ever posted to the GL must aggregate up here, and
 * total debits MUST equal total credits to the centavo.
 */
beforeEach(function () {
    $this->builder = new TrialBalanceBuilder();
    $this->period  = ReportPeriod::asOf(new DateTimeImmutable('2026-05-31'));
    $this->companyId = '018f0000-0000-7000-8000-000000000001';
});

it('signs debit-normal balances as (debit − credit)', function () {
    // Cash: debit-normal, ₱100,000 debits / ₱30,000 credits → balance +₱70,000
    $rows = [[
        'account_id'     => '018f0000-0000-7000-8000-000000000010',
        'account_code'   => '1010',
        'account_name'   => 'Cash on Hand',
        'account_type'   => 'asset',
        'normal_balance' => 'debit',
        'total_debit'    => '100000.00',
        'total_credit'   => '30000.00',
    ]];

    $tb = $this->builder->build($this->companyId, $this->period, $rows);

    expect($tb->lines)->toHaveCount(1)
        ->and($tb->lines[0]->balance)->toBe('70000.00')
        ->and($tb->totalDebit)->toBe('100000.00')
        ->and($tb->totalCredit)->toBe('30000.00');
});

it('signs credit-normal balances as (credit − debit)', function () {
    // Accounts Payable: credit-normal, ₱20,000 debits / ₱50,000 credits → balance +₱30,000
    $rows = [[
        'account_id'     => '018f0000-0000-7000-8000-000000000020',
        'account_code'   => '2010',
        'account_name'   => 'Accounts Payable',
        'account_type'   => 'liability',
        'normal_balance' => 'credit',
        'total_debit'    => '20000.00',
        'total_credit'   => '50000.00',
    ]];

    $tb = $this->builder->build($this->companyId, $this->period, $rows);

    expect($tb->lines[0]->balance)->toBe('30000.00');         // (credit − debit), signed positive
});

it('skips accounts with zero activity on both sides', function () {
    $rows = [
        [
            'account_id' => 'a1', 'account_code' => '1010', 'account_name' => 'Cash',
            'account_type' => 'asset', 'normal_balance' => 'debit',
            'total_debit' => '1000.00', 'total_credit' => '0.00',
        ],
        [
            'account_id' => 'a2', 'account_code' => '1020', 'account_name' => 'Dormant',
            'account_type' => 'asset', 'normal_balance' => 'debit',
            'total_debit' => '0.00', 'total_credit' => '0.00',          // ← skipped
        ],
    ];

    $tb = $this->builder->build($this->companyId, $this->period, $rows);

    expect($tb->lines)->toHaveCount(1)
        ->and($tb->lines[0]->accountCode)->toBe('1010');
});

it('produces a balanced TB when every contra-side credit has a matching debit', function () {
    // Classic micro-TB: Cash 100,000 DR / Capital 100,000 CR
    $rows = [
        [
            'account_id' => 'a-cash', 'account_code' => '1010', 'account_name' => 'Cash',
            'account_type' => 'asset', 'normal_balance' => 'debit',
            'total_debit' => '100000.00', 'total_credit' => '0.00',
        ],
        [
            'account_id' => 'a-cap', 'account_code' => '3010', 'account_name' => 'Owner Capital',
            'account_type' => 'equity', 'normal_balance' => 'credit',
            'total_debit' => '0.00', 'total_credit' => '100000.00',
        ],
    ];

    $tb = $this->builder->build($this->companyId, $this->period, $rows);

    expect($tb->totalDebit)->toBe('100000.00')
        ->and($tb->totalCredit)->toBe('100000.00')
        ->and($tb->isBalanced())->toBeTrue();
});

it('flags an unbalanced TB (data corruption signal) without throwing', function () {
    // Builder should not "fix" a bad aggregator result — it surfaces it via isBalanced().
    $rows = [
        [
            'account_id' => 'a-cash', 'account_code' => '1010', 'account_name' => 'Cash',
            'account_type' => 'asset', 'normal_balance' => 'debit',
            'total_debit' => '100000.00', 'total_credit' => '0.00',
        ],
        [
            'account_id' => 'a-cap', 'account_code' => '3010', 'account_name' => 'Capital',
            'account_type' => 'equity', 'normal_balance' => 'credit',
            'total_debit' => '0.00', 'total_credit' => '99999.99',
        ],
    ];

    $tb = $this->builder->build($this->companyId, $this->period, $rows);

    expect($tb->isBalanced())->toBeFalse();
});

it('handles contra accounts (e.g., Accumulated Depreciation as credit-normal asset)', function () {
    // Accumulated Depreciation: contra-asset; normal_balance is credit even though account_type='asset'
    $rows = [[
        'account_id'     => 'a-accdep',
        'account_code'   => '1599',
        'account_name'   => 'Accumulated Depreciation – PPE',
        'account_type'   => 'contra_asset',
        'normal_balance' => 'credit',
        'total_debit'    => '0.00',
        'total_credit'   => '250000.00',
    ]];

    $tb = $this->builder->build($this->companyId, $this->period, $rows);

    expect($tb->lines[0]->balance)->toBe('250000.00')             // positive on credit side
        ->and($tb->lines[0]->accountType)->toBe('contra_asset');
});
