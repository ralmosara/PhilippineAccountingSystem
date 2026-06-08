<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Presentation\Http\Controllers;

use App\Modules\Accounting\Application\Actions\CreateJournalEntry;
use App\Modules\Accounting\Infrastructure\Persistence\Eloquent\JournalEntryModel;
use App\Modules\Accounting\Presentation\Http\Requests\StoreJournalEntryRequest;
use App\Modules\Accounting\Presentation\Http\Resources\JournalEntryResource;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Resource controller — only the 7 RESTful methods.
 *
 * Anything beyond CRUD (post, reverse, lock, void, file with BIR, transmit
 * to EIS, …) is its own single-action invokable controller.
 */
final class JournalEntryController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = JournalEntryModel::query()
            ->with('lines')
            ->where('company_id', $request->user()->company_id);

        if ($request->filled('from')) {
            $query->where('entry_date', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->where('entry_date', '<=', $request->date('to'));
        }
        if ($request->boolean('posted_only')) {
            $query->whereNotNull('posted_at');
        }

        return JournalEntryResource::collection(
            $query->orderByDesc('entry_date')
                  ->orderByDesc('sequence_no')
                  ->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Request $request, string $journal): JournalEntryResource
    {
        $model = JournalEntryModel::query()
            ->with(['lines', 'reversal'])
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($journal);

        return new JournalEntryResource($model);
    }

    public function create(Request $request): JsonResponse
    {
        // Returns form context: open periods, postable accounts, document series
        return new JsonResponse([
            'message' => 'Use GET /api/v1/accounts and /api/v1/fiscal-periods/open to populate form.',
        ]);
    }

    public function store(StoreJournalEntryRequest $request, CreateJournalEntry $action): JsonResponse
    {
        $entry = $action->execute(
            companyId:        $request->user()->company_id,
            documentSeriesId: $request->string('document_series_id')->toString(),
            entryDate:        new DateTimeImmutable($request->string('entry_date')->toString()),
            lines:            $request->validated('lines'),
            source:           $request->string('source', 'manual')->toString(),
            sourceDocId:      $request->input('source_doc_id'),
            sourceDocType:    $request->input('source_doc_type'),
            memo:             $request->input('memo'),
            actorId:          $request->user()->id,
        );

        return new JsonResponse(new JournalEntryResource($entry), 201);
    }

    public function edit(Request $request, string $journal): JsonResponse
    {
        $model = JournalEntryModel::query()
            ->with('lines')
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($journal);

        if ($model->posted_at !== null) {
            return new JsonResponse([
                'message' => 'Posted entries cannot be edited; create a reversal instead.',
            ], 423);
        }

        return new JsonResponse(['journal' => new JournalEntryResource($model)]);
    }

    public function update(Request $request, string $journal): JsonResponse
    {
        // Phase 1 stub — UpdateJournalEntry action will land in the next batch.
        // Editing draft journals is a thin variant of CreateJournalEntry.
        return new JsonResponse(['message' => 'UpdateJournalEntry action pending implementation.'], 501);
    }

    public function destroy(Request $request, string $journal): JsonResponse
    {
        $model = JournalEntryModel::query()
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($journal);

        if ($model->posted_at !== null) {
            return new JsonResponse([
                'message' => 'Posted entries cannot be deleted; reverse them instead.',
            ], 423);
        }

        $model->lines()->delete();
        $model->delete();

        return new JsonResponse(null, 204);
    }
}
