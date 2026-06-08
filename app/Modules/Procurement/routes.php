<?php

declare(strict_types=1);

use App\Modules\Procurement\Presentation\Http\Controllers\IssueForm2307Controller;
use App\Modules\Procurement\Presentation\Http\Controllers\PostVendorBillController;
use App\Modules\Procurement\Presentation\Http\Controllers\PurchaseOrderController;
use App\Modules\Procurement\Presentation\Http\Controllers\VendorBillController;
use App\Modules\Procurement\Presentation\Http\Controllers\VendorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Procurement module routes — wired by ModuleServiceProvider under /api/v1
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    // Resources (7 RESTful methods only)
    Route::apiResource('vendors',          VendorController::class);
    Route::apiResource('purchase-orders',  PurchaseOrderController::class);
    Route::apiResource('vendor-bills',     VendorBillController::class);

    // Single-action: post a vendor bill end-to-end (computes withholding,
    // posts JV, emits VendorBillPosted → AutoIssueForm2307 listener)
    Route::post('vendor-bills/post', PostVendorBillController::class)
         ->middleware('mfa');

    // Single-action: manually issue (or re-issue) a Form 2307 for a bill
    Route::post('vendor-bills/{bill}/issue-2307', IssueForm2307Controller::class)
         ->middleware('mfa');
});
