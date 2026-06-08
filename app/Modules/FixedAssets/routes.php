<?php

declare(strict_types=1);

use App\Modules\FixedAssets\Presentation\Http\Controllers\ComputeMonthlyDepreciationController;
use App\Modules\FixedAssets\Presentation\Http\Controllers\DisposeAssetController;
use App\Modules\FixedAssets\Presentation\Http\Controllers\FixedAssetController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('fixed-assets', FixedAssetController::class);
    Route::post('fixed-assets/depreciation/compute', ComputeMonthlyDepreciationController::class)->middleware('mfa');
    Route::post('fixed-assets/{asset}/dispose',      DisposeAssetController::class)->middleware('mfa');
});
