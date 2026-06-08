<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/auth/me
 *
 * Returns the currently authenticated user. Used by the React SPA to
 * rehydrate auth state after a page refresh — the bearer token in
 * localStorage is sent on every request, but the user payload itself
 * is fetched fresh here so we never trust stale name/email/role data.
 *
 * 401 if no/expired token. Frontend redirects to /login on that.
 */
final class GetAuthenticatedUserController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        return new JsonResponse([
            'user' => [
                'id'         => (string) $user->id,
                'email'      => (string) $user->email,
                'full_name'  => (string) $user->full_name,
                'company_id' => (string) $user->company_id,
                'roles'      => method_exists($user, 'getRoleNames')
                    ? $user->getRoleNames()->all()
                    : [],
                'permissions' => method_exists($user, 'getAllPermissions')
                    ? $user->getAllPermissions()->pluck('name')->all()
                    : [],
                'mfa_enabled' => (bool) ($user->mfa_enabled ?? false),
            ],
        ]);
    }
}
