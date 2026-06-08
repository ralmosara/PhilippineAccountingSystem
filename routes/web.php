<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return [
        'name' => config('app.name'),
        'message' => 'Philippine Accounting System API. Visit /api/v1 for the SPA backend.',
        'docs' => '/docs',
    ];
});
