<?php

declare(strict_types=1);

use App\Modules\Inventory\Presentation\Http\Controllers\AdjustStockController;
use App\Modules\Inventory\Presentation\Http\Controllers\GenerateInventoryListController;
use App\Modules\Inventory\Presentation\Http\Controllers\ItemController;
use App\Modules\Inventory\Presentation\Http\Controllers\RecordStockMovementController;
use App\Modules\Inventory\Presentation\Http\Controllers\StockMovementController;
use App\Modules\Inventory\Presentation\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Resources (7 RESTful methods only)
    Route::apiResource('items',            ItemController::class);
    Route::apiResource('warehouses',       WarehouseController::class);
    Route::apiResource('stock-movements',  StockMovementController::class)->only(['index', 'show']);

    // Single-action verbs
    Route::post('stock-movements/record',  RecordStockMovementController::class);
    Route::post('stock/adjust',            AdjustStockController::class)->middleware('mfa');

    // BIR Annual Inventory List
    Route::post('inventory/list/generate', GenerateInventoryListController::class)->middleware('mfa');
});
