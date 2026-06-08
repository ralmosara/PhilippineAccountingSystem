<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Presentation\Http\Controllers;

use App\Modules\Payroll\Application\Actions\RegisterLoanDeduction;
use App\Modules\Payroll\Application\Actions\SettleLoanDeduction;
use App\Modules\Payroll\Domain\ValueObjects\LoanType;
use App\Modules\Payroll\Infrastructure\Persistence\Eloquent\LoanDeductionModel;
use App\Modules\Payroll\Presentation\Http\Requests\RegisterLoanDeductionRequest;
use App\Modules\Payroll\Presentation\Http\Resources\LoanDeductionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

/**
 * Loan deduction resource — 7 RESTful methods.
 *
 * Loans are immutable after registration (settle + re-register if corrections
 * are needed), so update() returns 501. destroy() settles (marks inactive)
 * rather than hard-deleting to preserve audit history.
 */
final class LoanDeductionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = LoanDeductionModel::query()->orderByDesc('started_on');

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->string('employee_id'));
        }

        return LoanDeductionResource::collection($query->get());
    }

    public function create(): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Use POST /loan-deductions to register a loan deduction.',
        ], 501);
    }

    public function store(RegisterLoanDeductionRequest $request): JsonResponse
    {
        /** @var \App\Modules\Identity\Infrastructure\Persistence\Eloquent\UserModel $user */
        $user = $request->user();

        $action = app(RegisterLoanDeduction::class);

        $loan = $action->execute(
            employeeId:          $request->validated('employee_id'),
            companyId:           $user->company_id,
            loanType:            LoanType::from($request->validated('loan_type')),
            loanReference:       $request->validated('loan_reference'),
            originalAmount:      (string) $request->validated('original_amount'),
            monthlyAmortization: (string) $request->validated('monthly_amortization'),
            startedOn:           $request->validated('started_on'),
            endsOn:              $request->validated('ends_on'),
            actorId:             $user->id,
        );

        $model = LoanDeductionModel::query()->findOrFail($loan->id);

        return (new LoanDeductionResource($model))
            ->response()
            ->setStatusCode(201);
    }

    public function show(LoanDeductionModel $loanDeduction): LoanDeductionResource
    {
        return new LoanDeductionResource($loanDeduction);
    }

    public function edit(LoanDeductionModel $loanDeduction): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Loans are not amended. Settle and re-register if a correction is needed.',
        ], 501);
    }

    public function update(Request $request, LoanDeductionModel $loanDeduction): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Loans are not amended. Settle and re-register if a correction is needed.',
        ], 501);
    }

    public function destroy(LoanDeductionModel $loanDeduction): JsonResponse
    {
        /** @var \App\Modules\Identity\Infrastructure\Persistence\Eloquent\UserModel $user */
        $user = request()->user();

        $action = app(SettleLoanDeduction::class);
        $action->execute(loanId: $loanDeduction->id, actorId: $user->id);

        return new JsonResponse(['message' => 'Loan deduction settled.'], 200);
    }
}
