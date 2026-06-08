<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Roles that handle financial decisions (Approver, Auditor, Admin) MUST
 * have MFA enabled. Routes that touch posting, voiding, filing, or payroll
 * approval should be tagged with this middleware.
 */
final class EnsureMfaForSensitiveRoles
{
    private const SENSITIVE_ROLES = ['Admin', 'Approver', 'Auditor'];

    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if (! $user) {
            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        $hasSensitiveRole = collect(self::SENSITIVE_ROLES)
            ->contains(fn (string $role) => $user->hasRole($role));

        if ($hasSensitiveRole && ! $user->mfa_enabled) {
            return new JsonResponse(
                ['message' => 'MFA is required for your role. Enable MFA before continuing.'],
                403,
            );
        }

        return $next($request);
    }
}
