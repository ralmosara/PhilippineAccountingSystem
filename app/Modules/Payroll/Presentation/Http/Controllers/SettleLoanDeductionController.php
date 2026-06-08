<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Presentation\Http\Controllers;

use App\Modules\Payroll\Application\Actions\SettleLoanDeduction;
use App\Modules\Payroll\Infrastructure\Persistence\Eloquent\LoanDeductionModel;
use App\Modules\Payroll\Presentation\Http\Resources\LoanDeductionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * POST /loan-deductions/{loanDeduction}/settle
 *
 * Single-action controller that marks a loan as settled (inactive) without
 * hard-deleting the record. Requires payroll.runs.compute permission.
 */
final class SettleLoanDeductionController extends Controller
{
    public function __invoke(
        Request $request,
        SettleLoanDeduction $action,
        LoanDeductionModel $loanDeduction,
    ): JsonResponse {
        Gate::authorize('payroll.runs.compute');

        try {
            $settled = $action->execute(
                loanId:  $loanDeduction->id,
                actorId: $request->user()->id,
            );
        } catch (\DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 409);
        }

        $model = LoanDeductionModel::query()->findOrFail($settled->id);

        return new JsonResponse(new LoanDeductionResource($model), 200);
    }
}
