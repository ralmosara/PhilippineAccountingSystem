<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use Closure;
use Illuminate\Http\Request;

/**
 * HTTP-level audit trail. Optional — Actions emit audit events directly
 * via AuditWriterContract for fine-grained payloads. This middleware is
 * useful for sensitive READ endpoints where we want to record access
 * (e.g. exporting alphalist, viewing payroll).
 *
 * Usage:
 *     Route::get('/payroll/export', ExportPayrollController::class)
 *         ->middleware(['auth:sanctum', 'audit:payroll.exported']);
 */
final readonly class WriteAuditTrail
{
    public function __construct(private AuditWriterContract $audit)
    {
    }

    public function handle(Request $request, Closure $next, string $eventType = 'http.request'): mixed
    {
        $response = $next($request);

        $user = $request->user();
        if ($user && $request->method() !== 'OPTIONS') {
            $this->audit->writeEvent(
                actorId:     $user->id,
                companyId:   $user->company_id,
                eventType:   $eventType,
                aggregate:   'HttpRequest',
                aggregateId: (string) $request->fingerprint(),
                payload:     [
                    'method' => $request->method(),
                    'path'   => $request->path(),
                    'status' => $response->getStatusCode(),
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                requestId: $request->header('X-Request-Id'),
            );
        }

        return $response;
    }
}
