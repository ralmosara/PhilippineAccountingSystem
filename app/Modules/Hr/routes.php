<?php

declare(strict_types=1);

use App\Modules\Hr\Presentation\Http\Controllers\ApproveLeaveRequestController;
use App\Modules\Hr\Presentation\Http\Controllers\DepartmentController;
use App\Modules\Hr\Presentation\Http\Controllers\EmployeeController;
use App\Modules\Hr\Presentation\Http\Controllers\LeaveRequestController;
use App\Modules\Hr\Presentation\Http\Controllers\PositionController;
use App\Modules\Hr\Presentation\Http\Controllers\RejectLeaveRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('employees',   EmployeeController::class);
    Route::apiResource('departments', DepartmentController::class);
    Route::apiResource('positions',   PositionController::class);

    Route::apiResource('leave-requests', LeaveRequestController::class);
    Route::post('leave-requests/{leaveRequest}/approve', ApproveLeaveRequestController::class);
    Route::post('leave-requests/{leaveRequest}/reject', RejectLeaveRequestController::class);
});
