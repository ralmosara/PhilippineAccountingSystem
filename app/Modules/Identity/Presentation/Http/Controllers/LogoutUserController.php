<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LogoutUserController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return new JsonResponse(['message' => 'Signed out.']);
    }
}
