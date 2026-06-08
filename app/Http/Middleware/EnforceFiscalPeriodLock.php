<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Application-level guard against writing to a locked fiscal period.
 *
 * The DB-level trigger (accounting.reject_locked_period_writes — added by
 * the Accounting module's migration) is the source of truth; this middleware
 * provides a friendly 423 (Locked) response instead of a 500 from a Postgres
 * exception when the UI hits a locked-period write path.
 *
 * Tag specific routes with this middleware (e.g. PostJournalEntryController,
 * IssueOfficialReceiptController, RunPayrollController). Read endpoints
 * don't need it.
 */
final class EnforceFiscalPeriodLock
{
    public function handle(Request $request, Closure $next): mixed
    {
        // Phase 0 stub — full check happens in the DB trigger.
        // The Accounting module will set this to inspect the requested
        // fiscal_period_id from the route and reject early.
        return $next($request);
    }

    private function lockedResponse(): JsonResponse
    {
        return new JsonResponse(
            ['message' => 'The requested fiscal period is locked.'],
            423,
        );
    }
}
