<?php

declare(strict_types=1);

use App\Modules\Reporting\Domain\Services\IncomeStatementBuilder;
use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;

/**
 * P&L classification follows PFRS section ordering:
 *   Revenue − COGS = Gross Profit
 *   Gross Profit − OpEx = Operating Income
 *   Operating Income + Other Income − Other Expense = Income Before Tax
 *   Income Before Tax − Income Tax = Net Income
 */
beforeEach(function () {
    $this->builder   = new IncomeStatementBuilder();
    $this->period    = ReportPeriod::forPeriod(
        new DateTimeImmutable('2026-01-01'),
        new DateTimeImmutable('2026-03-31'),
    );
    $this->companyId = '018f0000-0000-7000-8000-000000000001';
});

function isRow(string $id, string $code, string $name, string $cls, string $type, string $balance): array
{
    return [
        'account_id'          => $id,
        'account_code'        => $code,
        'account_name'        => $name,
        'account_type'        => $type,
        'pfrs_classification' => $cls,
        'normal_balance'      => in_array($type, ['revenue'], true) ? 'credit' : 'debit',
        'balance'             => $balance,
    ];
}

it('classifies lines into the 6 PFRS sections by pfrs_classification', function () {
    $rows = [
        isRow('a1', '4010', 'Sales',              'operating_revenue', 'revenue', '1000000.00'),
        isRow('a2', '5010', 'Cost of Goods Sold', 'cost_of_sales',     'expense', '600000.00'),
        isRow('a3', '6010', 'Salaries',           'operating_expense', 'expense', '200000.00'),
        isRow('a4', '7010', 'Interest Income',    'other_income',      'revenue', '5000.00'),
        isRow('a5', '7020', 'Interest Expense',   'other_expense',     'expense', '8000.00'),
        isRow('a6', '8010', 'Income Tax Exp.',    'income_tax',        'expense', '40000.00'),
    ];

    $is = $this->builder->build($this->companyId, $this->period, $rows);

    expect($is->totalRevenue)->toBe('1000000.00')
        ->and($is->totalCostOfSales)->toBe('600000.00')
        ->and($is->totalOperatingExpenses)->toBe('200000.00')
        ->and($is->totalOtherIncome)->toBe('5000.00')
        ->and($is->totalOtherExpenses)->toBe('8000.00')
        ->and($is->totalIncomeTax)->toBe('40000.00');
});

it('computes gross profit = revenue − COGS', function () {
    $rows = [
        isRow('a1', '4010', 'Sales',     'operating_revenue', 'revenue', '1000000.00'),
        isRow('a2', '5010', 'COGS',      'cost_of_sales',     'expense', '600000.00'),
    ];
    $is = $this->builder->build($this->companyId, $this->period, $rows);
    expect($is->grossProfit())->toBe('400000.00');
});

it('computes operating income = gross profit − opex', function () {
    $rows = [
        isRow('a1', '4010', 'Sales', 'operating_revenue', 'revenue', '1000000.00'),
        isRow('a2', '5010', 'COGS',  'cost_of_sales',     'expense', '600000.00'),
        isRow('a3', '6010', 'Rent',  'operating_expense', 'expense', '150000.00'),
    ];
    $is = $this->builder->build($this->companyId, $this->period, $rows);
    expect($is->operatingIncome())->toBe('250000.00');           // 400k − 150k
});

it('computes income before tax = operating + other income − other expenses', function () {
    $rows = [
        isRow('a1', '4010', 'Sales',    'operating_revenue', 'revenue', '1000000.00'),
        isRow('a2', '5010', 'COGS',     'cost_of_sales',     'expense', '600000.00'),
        isRow('a3', '6010', 'Rent',     'operating_expense', 'expense', '150000.00'),
        isRow('a4', '7010', 'Int Inc',  'other_income',      'revenue', '20000.00'),
        isRow('a5', '7020', 'Int Exp',  'other_expense',     'expense', '30000.00'),
    ];
    $is = $this->builder->build($this->companyId, $this->period, $rows);

    // operating 250,000 − other_exp 30,000 + other_inc 20,000 = 240,000
    expect($is->incomeBeforeTax())->toBe('240000.00');
});

it('computes net income = income before tax − income tax', function () {
    $rows = [
        isRow('a1', '4010', 'Sales',     'operating_revenue', 'revenue', '1000000.00'),
        isRow('a2', '8010', 'Tax Exp.',  'income_tax',        'expense', '250000.00'),
    ];
    $is = $this->builder->build($this->companyId, $this->period, $rows);

    expect($is->incomeBeforeTax())->toBe('1000000.00')
        ->and($is->netIncome())->toBe('750000.00');
});

it('treats finance_cost as another flavour of other_expense', function () {
    $rows = [
        isRow('a1', '4010', 'Sales',         'operating_revenue', 'revenue', '500000.00'),
        isRow('a2', '7030', 'Loan Interest', 'finance_cost',      'expense', '12000.00'),
    ];
    $is = $this->builder->build($this->companyId, $this->period, $rows);

    expect($is->totalOtherExpenses)->toBe('12000.00')
        ->and($is->otherExpenses)->toHaveCount(1);
});

it('falls back to account_type when pfrs_classification is null (unclassified migration safety)', function () {
    $rows = [
        [
            'account_id'          => 'a1',
            'account_code'        => '4010',
            'account_name'        => 'Misc Revenue',
            'account_type'        => 'revenue',
            'pfrs_classification' => null,                    // not yet PFRS-tagged
            'normal_balance'      => 'credit',
            'balance'             => '50000.00',
        ],
        [
            'account_id'          => 'a2',
            'account_code'        => '6010',
            'account_name'        => 'Misc Expense',
            'account_type'        => 'expense',
            'pfrs_classification' => null,
            'normal_balance'      => 'debit',
            'balance'             => '20000.00',
        ],
    ];
    $is = $this->builder->build($this->companyId, $this->period, $rows);

    expect($is->totalRevenue)->toBe('50000.00')
        ->and($is->totalOperatingExpenses)->toBe('20000.00');
});

it('ignores asset/liability/equity lines (they belong on the Balance Sheet, not P&L)', function () {
    $rows = [
        [
            'account_id' => 'a1', 'account_code' => '1010', 'account_name' => 'Cash',
            'account_type' => 'asset', 'pfrs_classification' => null,
            'normal_balance' => 'debit', 'balance' => '100000.00',
        ],
    ];
    $is = $this->builder->build($this->companyId, $this->period, $rows);

    expect($is->totalRevenue)->toBe('0.00')
        ->and($is->totalOperatingExpenses)->toBe('0.00')
        ->and($is->netIncome())->toBe('0.00');
});

it('drops zero-balance accounts so the IS does not show dormant chart rows', function () {
    $rows = [
        isRow('a1', '4010', 'Sales',  'operating_revenue', 'revenue', '1000000.00'),
        isRow('a2', '4020', 'Unused', 'operating_revenue', 'revenue', '0.00'),
    ];
    $is = $this->builder->build($this->companyId, $this->period, $rows);

    expect($is->revenue)->toHaveCount(1)
        ->and($is->revenue[0]->accountCode)->toBe('4010');
});
