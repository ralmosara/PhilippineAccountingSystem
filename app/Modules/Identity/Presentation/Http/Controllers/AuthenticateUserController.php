<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Controllers;

use App\Modules\Identity\Application\Actions\AuthenticateUser;
use App\Modules\Identity\Application\Exceptions\InvalidCredentialsException;
use App\Modules\Identity\Presentation\Http\Requests\AuthenticateUserRequest;
use App\Modules\Identity\Presentation\Http\Resources\AuthenticatedUserResource;
use Illuminate\Http\JsonResponse;

/**
 * Single-action invokable controller (Taylor Otwell convention).
 *
 * One verb = one controller. The class has exactly one public method
 * (__invoke). Logic lives in the Action; this controller is a thin
 * HTTP adapter: validate → resolve → respond.
 */
final class AuthenticateUserController
{
    public function __invoke(
        AuthenticateUserRequest $request,
        AuthenticateUser $action,
    ): JsonResponse {
        try {
            $result = $action->execute(
                email:      $request->string('email')->toString(),
                password:   $request->string('password')->toString(),
                deviceName: $request->userAgent() ?? 'unknown',
                ipAddress:  $request->ip() ?? '0.0.0.0',
            );
        } catch (InvalidCredentialsException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 401);
        }

        return new JsonResponse(new AuthenticatedUserResource($result), 200);
    }
}
