<?php

declare(strict_types=1);

use App\Modules\Identity\Presentation\Http\Controllers\AuthenticateUserController;
use App\Modules\Identity\Presentation\Http\Controllers\ConfirmMfaController;
use App\Modules\Identity\Presentation\Http\Controllers\EnableMfaController;
use App\Modules\Identity\Presentation\Http\Controllers\GetAuthenticatedUserController;
use App\Modules\Identity\Presentation\Http\Controllers\LogoutUserController;
use App\Modules\Identity\Presentation\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Identity module routes — wired by ModuleServiceProvider under /api/v1
|--------------------------------------------------------------------------
|
| Convention (Taylor Otwell):
|   * Resource controllers expose the 7 RESTful methods only.
|   * Every other verb is its own single-action invokable controller.
|
*/

// --- Public (no auth) ---
Route::post('/auth/login',   AuthenticateUserController::class);
Route::post('/auth/mfa',     ConfirmMfaController::class)->middleware('auth:sanctum');

// --- Authenticated ---
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', GetAuthenticatedUserController::class);
    Route::post('/auth/logout', LogoutUserController::class);
    Route::post('/auth/mfa/enable', EnableMfaController::class);

    // Resource: 7 RESTful methods only on UserController
    Route::apiResource('users', UserController::class);
});
