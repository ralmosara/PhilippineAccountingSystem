<?php

declare(strict_types=1);

use App\Modules\Accounting\Presentation\Http\Controllers\ChartOfAccountsController;
use App\Modules\Accounting\Presentation\Http\Controllers\JournalEntryController;
use App\Modules\Accounting\Presentation\Http\Controllers\LockFiscalPeriodController;
use App\Modules\Accounting\Presentation\Http\Controllers\PostJournalEntryController;
use App\Modules\Accounting\Presentation\Http\Controllers\ReverseJournalEntryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Accounting module routes — wired by ModuleServiceProvider under /api/v1
|--------------------------------------------------------------------------
|
| Convention (Taylor Otwell):
|   * Resource controllers expose the 7 RESTful methods only.
|   * Every other verb is its own single-action invokable controller.
|
*/

Route::middleware('auth:sanctum')->group(function () {
    // Resource: Chart of Accounts (7 RESTful methods)
    Route::apiResource('accounts', ChartOfAccountsController::class);

    // Resource: Journal Entries (7 RESTful methods)
    Route::apiResource('journals', JournalEntryController::class);

    // Single-action verbs on journal entries
    Route::post('journals/{journal}/post',    PostJournalEntryController::class)
         ->middleware('mfa');                          // requires MFA for sensitive roles
    Route::post('journals/{journal}/reverse', ReverseJournalEntryController::class)
         ->middleware('mfa');

    // Single-action: lock a fiscal period
    Route::post('fiscal-periods/{period}/lock', LockFiscalPeriodController::class)
         ->middleware('mfa');
});
