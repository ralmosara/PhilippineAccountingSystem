<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Integration');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeBalancedJournal', function () {
    $entry = $this->value;
    $debits  = collect($entry->lines)->sum('debit');
    $credits = collect($entry->lines)->sum('credit');

    return $this->and($debits)->toEqualWithDelta($credits, 0.0001);
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function php(int|float $amount): string
{
    return number_format($amount, 2, '.', '');
}
