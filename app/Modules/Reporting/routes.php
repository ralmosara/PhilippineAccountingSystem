<?php

declare(strict_types=1);

use App\Modules\Reporting\Presentation\Http\Controllers\GenerateBalanceSheetController;
use App\Modules\Reporting\Presentation\Http\Controllers\GenerateCashDisbursementsBookController;
use App\Modules\Reporting\Presentation\Http\Controllers\GenerateCashFlowStatementController;
use App\Modules\Reporting\Presentation\Http\Controllers\GenerateCashReceiptsBookController;
use App\Modules\Reporting\Presentation\Http\Controllers\GenerateEquityStatementController;
use App\Modules\Reporting\Presentation\Http\Controllers\GenerateGeneralJournalController;
use App\Modules\Reporting\Presentation\Http\Controllers\GenerateGeneralLedgerController;
use App\Modules\Reporting\Presentation\Http\Controllers\GenerateIncomeStatementController;
use App\Modules\Reporting\Presentation\Http\Controllers\GeneratePurchasesBookController;
use App\Modules\Reporting\Presentation\Http\Controllers\GenerateSalesBookController;
use App\Modules\Reporting\Presentation\Http\Controllers\GenerateTrialBalanceController;
use App\Modules\Reporting\Presentation\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Read-only resource — list + show generated reports
    Route::apiResource('reports', ReportController::class);

    // Financial statements (PFRS Sections 4-7)
    Route::post('reports/trial-balance',    GenerateTrialBalanceController::class);
    Route::post('reports/balance-sheet',    GenerateBalanceSheetController::class);
    Route::post('reports/income-statement', GenerateIncomeStatementController::class);
    Route::post('reports/cash-flow',        GenerateCashFlowStatementController::class);
    Route::post('reports/equity-statement', GenerateEquityStatementController::class);

    // BIR-mandated Books of Accounts (RR 9-2009 / CAS)
    Route::post('reports/books/general-journal',          GenerateGeneralJournalController::class);
    Route::post('reports/books/general-ledger',           GenerateGeneralLedgerController::class);
    Route::post('reports/books/sales-book',               GenerateSalesBookController::class);
    Route::post('reports/books/purchases-book',           GeneratePurchasesBookController::class);
    Route::post('reports/books/cash-receipts-book',       GenerateCashReceiptsBookController::class);
    Route::post('reports/books/cash-disbursements-book',  GenerateCashDisbursementsBookController::class);
});
