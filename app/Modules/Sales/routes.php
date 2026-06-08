<?php

declare(strict_types=1);

use App\Modules\Sales\Presentation\Http\Controllers\CustomerController;
use App\Modules\Sales\Presentation\Http\Controllers\GetCustomerArAgingController;
use App\Modules\Sales\Presentation\Http\Controllers\IssueOfficialReceiptController;
use App\Modules\Sales\Presentation\Http\Controllers\IssueSalesInvoiceController;
use App\Modules\Sales\Presentation\Http\Controllers\SalesInvoiceController;
use App\Modules\Sales\Presentation\Http\Controllers\TransmitInvoiceToEisController;
use App\Modules\Sales\Presentation\Http\Controllers\VoidSalesInvoiceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sales module routes — wired by ModuleServiceProvider under /api/v1
|--------------------------------------------------------------------------
|
| Convention (Taylor Otwell):
|   * Resource controllers expose the 7 RESTful methods only.
|   * Every other verb (issue, void, transmit-to-EIS) is its own
|     single-action invokable controller.
|
*/

Route::middleware('auth:sanctum')->group(function () {
    // Resources (7 methods only)
    Route::apiResource('customers',       CustomerController::class);
    Route::apiResource('sales-invoices',  SalesInvoiceController::class);

    // Single-action: issue an SI end-to-end (allocate doc_no, compute VAT,
    // post JV, emit InvoiceIssued, queue EIS submission)
    Route::post('sales-invoices/issue', IssueSalesInvoiceController::class);

    // Single-action: void a posted SI (creates reversal JV, soft-voids invoice)
    Route::post('sales-invoices/{invoice}/void', VoidSalesInvoiceController::class)
         ->middleware('mfa');

    // Single-action: manual EIS retransmit (auditor / ops use)
    Route::post('sales-invoices/{invoice}/eis/transmit', TransmitInvoiceToEisController::class)
         ->middleware('mfa');

    // Single-action: issue an Official Receipt
    Route::post('official-receipts/issue', IssueOfficialReceiptController::class);

    // Read-only: per-customer AR aging snapshot (replaces client-side approximation)
    Route::get('customers/{customer}/ar-aging', GetCustomerArAgingController::class);
});
