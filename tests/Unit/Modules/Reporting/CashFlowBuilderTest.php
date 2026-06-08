<?php

declare(strict_types=1);

use App\Modules\Reporting\Domain\Services\CashFlowBuilder;
use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;

/**
 * Cash Flow Statement (PAS 7) — direct method, 3 sections.
 * The builder consumes pre-classified lines from the aggregator;
 * its responsibility is summation + reconciliation, NOT classification.
 */
beforeEach(function () {
    $this->builder = new CashFlowBuilder();
    $this->period  = ReportPeriod::forPeriod(
        new DateTimeImmutable('2026-01-01'),
        new DateTimeImmutable('2026-03-31'),
    );
    $this->companyId = '018f0000-0000-7000-8000-000000000001';
});

it('assembles a 3-section statement and totals each section', function () {
    $data = [
        'operating' => [
            ['description' => 'Cash received from customers',  'amount' => '1500000.00'],
            ['description' => 'Cash paid to suppliers',        'amount' => '-800000.00'],
            ['description' => 'Cash paid for operating exp.',  'amount' => '-200000.00'],
        ],
        'investing' => [
            ['description' => 'Purchase of equipment',         'amount' => '-300000.00'],
        ],
        'financing' => [
            ['description' => 'Proceeds from bank loan',       'amount' => '500000.00'],
            ['description' => 'Owner withdrawals',             'amount' => '-100000.00'],
        ],
        'beginning_cash' => '200000.00',
        'ending_cash'    => '800000.00',
    ];

    $cf = $this->builder->build($this->companyId, $this->period, $data);

    expect($cf->totalOperating)->toBe('500000.00')
        ->and($cf->totalInvesting)->toBe('-300000.00')
        ->and($cf->totalFinancing)->toBe('400000.00')
        ->and($cf->netChange())->toBe('600000.00');
});

it('reconciles when beginning + net change == ending cash', function () {
    $data = [
        'operating' => [['description' => 'Net operating cash', 'amount' => '500000.00']],
        'investing' => [['description' => 'PPE outflow',         'amount' => '-300000.00']],
        'financing' => [['description' => 'Net financing cash',  'amount' => '400000.00']],
        'beginning_cash' => '200000.00',
        'ending_cash'    => '800000.00',                                       // 200k + 600k
    ];

    $cf = $this->builder->build($this->companyId, $this->period, $data);

    expect($cf->reconciles())->toBeTrue();
});

it('fails reconciliation when net change does not bridge beginning to ending cash', function () {
    // Aggregator bug or missing classification — builder must surface the discrepancy.
    $data = [
        'operating' => [['description' => 'Operating', 'amount' => '500000.00']],
        'investing' => [],
        'financing' => [],
        'beginning_cash' => '200000.00',
        'ending_cash'    => '999999.00',                                       // does NOT match
    ];

    $cf = $this->builder->build($this->companyId, $this->period, $data);

    expect($cf->reconciles())->toBeFalse();
});

it('carries beginning and ending cash through unchanged for footnote display', function () {
    $data = [
        'operating' => [], 'investing' => [], 'financing' => [],
        'beginning_cash' => '123456.78',
        'ending_cash'    => '123456.78',
    ];

    $cf = $this->builder->build($this->companyId, $this->period, $data);

    expect($cf->beginningCash)->toBe('123456.78')
        ->and($cf->endingCash)->toBe('123456.78')
        ->and($cf->netChange())->toBe('0.00')
        ->and($cf->reconciles())->toBeTrue();                                  // zero change is valid
});

it('preserves line ordering within each section (display fidelity)', function () {
    $data = [
        'operating' => [
            ['description' => 'Collections from customers', 'amount' => '1000000.00'],
            ['description' => 'Payments to vendors',        'amount' => '-700000.00'],
            ['description' => 'Tax payments to BIR',        'amount' => '-50000.00'],
        ],
        'investing' => [], 'financing' => [],
        'beginning_cash' => '0.00', 'ending_cash' => '250000.00',
    ];

    $cf = $this->builder->build($this->companyId, $this->period, $data);

    expect($cf->operatingActivities[0]['description'])->toBe('Collections from customers')
        ->and($cf->operatingActivities[2]['description'])->toBe('Tax payments to BIR');
});
