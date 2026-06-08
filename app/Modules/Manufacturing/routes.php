<?php

declare(strict_types=1);

use App\Modules\Manufacturing\Presentation\Http\Controllers\BomController;
use App\Modules\Manufacturing\Presentation\Http\Controllers\CancelWorkOrderController;
use App\Modules\Manufacturing\Presentation\Http\Controllers\CompleteProductionRunController;
use App\Modules\Manufacturing\Presentation\Http\Controllers\StartWorkOrderController;
use App\Modules\Manufacturing\Presentation\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Manufacturing module routes — wired by ModuleServiceProvider under /api/v1
|--------------------------------------------------------------------------
|
| Convention (Taylor Otwell):
|   * Resource controllers expose the 7 RESTful methods only.
|   * Every other verb is its own single-action invokable controller.
|
*/

Route::middleware('auth:sanctum')->group(function () {
    // Bills of Materials — CRUD (7 RESTful methods)
    Route::apiResource('boms', BomController::class);

    // Work Orders — CRUD (7 RESTful methods)
    Route::apiResource('work-orders', WorkOrderController::class);

    // Single-action verbs on work orders
    Route::post('work-orders/{order}/start',    StartWorkOrderController::class)->middleware('mfa');
    Route::post('work-orders/{order}/complete', CompleteProductionRunController::class)->middleware('mfa');
    Route::post('work-orders/{order}/cancel',   CancelWorkOrderController::class);
});
