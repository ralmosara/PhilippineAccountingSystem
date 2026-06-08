<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Presentation\Http\Controllers;

use App\Modules\Accounting\Application\Actions\ReverseJournalEntry;
use App\Modules\Accounting\Application\Exceptions\FiscalPeriodLockedException;
use App\Modules\Accounting\Application\Exceptions\JournalNotFoundException;
use App\Modules\Accounting\Presentation\Http\Requests\ReverseJournalEntryRequest;
use App\Modules\Accounting\Presentation\Http\Resources\JournalEntryResource;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;

final class ReverseJournalEntryController
{
    public function __invoke(
        ReverseJournalEntryRequest $request,
        ReverseJournalEntry $action,
        string $journal,
    ): JsonResponse {
        try {
            $reversal = $action->execute(
                originalJournalEntryId: $journal,
                reversalDate: new DateTimeImmutable($request->string('reversal_date')->toString()),
                reason: $request->string('reason')->toString(),
                actorId: $request->user()->id,
            );
        } catch (JournalNotFoundException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 404);
        } catch (FiscalPeriodLockedException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 423);
        }

        return new JsonResponse(new JournalEntryResource($reversal), 201);
    }
}
