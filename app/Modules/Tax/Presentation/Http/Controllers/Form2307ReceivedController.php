<?php

declare(strict_types=1);

namespace App\Modules\Tax\Presentation\Http\Controllers;

use App\Modules\Tax\Application\Actions\ImportForm2307Received;
use App\Modules\Tax\Infrastructure\Persistence\Eloquent\Form2307ReceivedModel;
use App\Modules\Tax\Presentation\Http\Requests\Form2307ReceivedRequest;
use App\Modules\Tax\Presentation\Http\Resources\Form2307ReceivedResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Resource controller for 2307 certificates RECEIVED from customers/payors.
 * Strict Taylor Otwell shape: 7 RESTful methods only. Verbs like 'reject'
 * and 'reclaim' live in their own single-action controllers.
 */
final class Form2307ReceivedController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $companyId = $request->user()->company_id;
        $q = Form2307ReceivedModel::query()->where('company_id', $companyId);

        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }
        if ($request->filled('payor_tin')) {
            // accept dashed or unformatted
            $q->where('payor_tin', 'LIKE', '%'.preg_replace('/\D/', '', (string) $request->string('payor_tin')).'%');
        }
        if ($request->filled('atc_code')) {
            $q->where('atc_code', $request->string('atc_code'));
        }
        if ($request->filled('year')) {
            $q->whereYear('period_to', $request->integer('year'));
        }
        if ($request->filled('quarter')) {
            $year = $request->integer('year', (int) date('Y'));
            $quarter = $request->integer('quarter');
            $q->whereBetween('period_to', $this->quarterBounds($year, $quarter));
        }

        return Form2307ReceivedResource::collection(
            $q->orderByDesc('period_to')
              ->orderBy('payor_tin')
              ->paginate($request->integer('per_page', 25)),
        );
    }

    public function show(Request $request, string $form): Form2307ReceivedResource
    {
        return new Form2307ReceivedResource(
            Form2307ReceivedModel::query()
                ->where('company_id', $request->user()->company_id)
                ->findOrFail($form),
        );
    }

    public function create(): JsonResponse
    {
        // Static metadata for the React form (ATC codes are fetched from a separate endpoint)
        return new JsonResponse([
            'entry_methods' => ['manual', 'pdf_upload', 'csv_import'],
            'statuses'      => ['draft', 'recorded', 'claimed', 'rejected'],
        ]);
    }

    public function store(Form2307ReceivedRequest $request, ImportForm2307Received $import): JsonResponse
    {
        $cert = $import->execute($request->toInput());

        return new JsonResponse(
            new Form2307ReceivedResource(
                Form2307ReceivedModel::query()->findOrFail($cert->id->value),
            ),
            201,
        );
    }

    public function edit(Request $request, string $form): Form2307ReceivedResource
    {
        // Returns the row as-is; the React edit form pre-populates from this.
        return $this->show($request, $form);
    }

    public function update(Form2307ReceivedRequest $request, string $form): JsonResponse
    {
        // Only 'recorded' (not yet claimed) certs may be edited. Edits are
        // restricted to non-identity fields — payor TIN + period + ATC + cert#
        // are the dedupe key and require delete + reinsert to change.
        $model = Form2307ReceivedModel::query()
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($form);

        if ($model->status !== 'recorded') {
            return new JsonResponse(
                ['message' => "Cannot edit a {$model->status} certificate. Reject and re-record instead."],
                423,
            );
        }

        $model->update([
            'payor_registered_name' => $request->input('payor_registered_name'),
            'payor_branch_code'     => $request->input('payor_branch_code', $model->payor_branch_code),
            'payor_address'         => $request->input('payor_address'),
            'income_payment'        => $request->input('income_payment'),
            'tax_withheld'          => $request->input('tax_withheld'),
            'source_pdf_path'       => $request->input('source_pdf_path'),
        ]);

        return new JsonResponse(new Form2307ReceivedResource($model->refresh()), 200);
    }

    public function destroy(Request $request, string $form): JsonResponse
    {
        $model = Form2307ReceivedModel::query()
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($form);

        // Claimed certs are immutable (BIR audit-trail requirement) — must
        // be rejected first and then have their parent ITR amended.
        if ($model->status === 'claimed') {
            return new JsonResponse(
                ['message' => 'Claimed certificates cannot be deleted; amend the linked ITR first.'],
                423,
            );
        }

        $model->delete();
        return new JsonResponse(null, 204);
    }

    /** @return array{0: string, 1: string} */
    private function quarterBounds(int $year, int $quarter): array
    {
        $startMonth = ($quarter - 1) * 3 + 1;
        $endMonth   = $startMonth + 2;
        $endDay     = (int) date('t', mktime(0, 0, 0, $endMonth, 1, $year));

        return [
            sprintf('%04d-%02d-01',    $year, $startMonth),
            sprintf('%04d-%02d-%02d', $year, $endMonth, $endDay),
        ];
    }
}
