<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Presentation\Http\Controllers;

use App\Modules\Accounting\Application\Actions\PostJournalEntry;
use App\Modules\Accounting\Application\Exceptions\FiscalPeriodLockedException;
use App\Modules\Accounting\Application\Exceptions\JournalNotFoundException;
use App\Modules\Accounting\Domain\Exceptions\UnbalancedJournalException;
use App\Modules\Accounting\Presentation\Http\Resources\JournalEntryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Single-action invokable controller (Taylor Otwell convention).
 *
 *   POST /api/v1/journals/{journal}/post
 *
 * Verbs in accounting that aren't CRUD (post, reverse, lock period, …)
 * each get their own controller class with a single __invoke method.
 */
final class PostJournalEntryController
{
    public function __invoke(
        Request $request,
        PostJournalEntry $action,
        string $journal,
    ): JsonResponse {
        try {
            $entry = $action->execute(
                journalEntryId: $journal,
                actorId: $request->user()->id,
            );
        } catch (JournalNotFoundException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 404);
        } catch (FiscalPeriodLockedException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 423);
        } catch (UnbalancedJournalException $e) {
            return new JsonResponse([
                'message' => $e->getMessage(),
                'debits'  => $e->debitsTotal,
                'credits' => $e->creditsTotal,
            ], 422);
        }

        return new JsonResponse(new JournalEntryResource($entry), 200);
    }
}
