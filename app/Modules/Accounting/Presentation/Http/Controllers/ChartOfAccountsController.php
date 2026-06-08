<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Presentation\Http\Controllers;

use App\Modules\Accounting\Infrastructure\Persistence\Eloquent\AccountModel;
use App\Modules\Accounting\Presentation\Http\Resources\AccountResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * CoA resource — 7 RESTful methods only.
 */
final class ChartOfAccountsController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = AccountModel::query()
            ->where('company_id', $request->user()->company_id);

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }
        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }
        if ($request->boolean('postable_only')) {
            $query->where('is_postable', true);
        }

        return AccountResource::collection(
            $query->orderBy('code')->paginate($request->integer('per_page', 200))
        );
    }

    public function show(Request $request, string $account): AccountResource
    {
        return new AccountResource(
            AccountModel::query()
                ->where('company_id', $request->user()->company_id)
                ->findOrFail($account)
        );
    }

    public function create(): JsonResponse
    {
        return new JsonResponse([
            'types' => [
                'asset', 'liability', 'equity', 'revenue', 'expense',
                'contra_asset', 'contra_liability', 'contra_equity',
            ],
            'normal_balances' => ['debit', 'credit'],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        // Stub — CreateAccount action will land in the next batch
        return new JsonResponse(['message' => 'CreateAccount action pending implementation.'], 501);
    }

    public function edit(Request $request, string $account): JsonResponse
    {
        return new JsonResponse(['account' => $this->show($request, $account)]);
    }

    public function update(Request $request, string $account): JsonResponse
    {
        return new JsonResponse(['message' => 'UpdateAccount action pending implementation.'], 501);
    }

    public function destroy(Request $request, string $account): JsonResponse
    {
        return new JsonResponse(['message' => 'DeleteAccount action pending implementation.'], 501);
    }
}
