<?php

declare(strict_types=1);

use App\Modules\Projects\Presentation\Http\Controllers\CloseProjectController;
use App\Modules\Projects\Presentation\Http\Controllers\ProjectController;
use App\Modules\Projects\Presentation\Http\Controllers\RecognizeWipController;
use App\Modules\Projects\Presentation\Http\Controllers\TimesheetEntryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('projects', ProjectController::class);
    Route::apiResource('projects.timesheets', TimesheetEntryController::class)->only(['index', 'store']);
    Route::post('projects/{project}/recognize-wip', RecognizeWipController::class)->middleware('mfa');
    Route::post('projects/{project}/close',         CloseProjectController::class)->middleware('mfa');
});
