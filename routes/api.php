<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (top-level, /api/v1)
|--------------------------------------------------------------------------
|
| Per-module routes are wired by ModuleServiceProvider from
| app/Modules/<Name>/routes.php. This file holds shared concerns only:
|
|   - /me        : current user
|   - /health/*  : module health probes
|   - /version   : build info
|
*/

Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return $request->user()->load('roles', 'permissions');
});

Route::get('/version', function () {
    return [
        'app'     => config('app.name'),
        'env'     => app()->environment(),
        'version' => config('app.version', 'dev'),
        'php'     => PHP_VERSION,
        'laravel' => app()->version(),
    ];
});
