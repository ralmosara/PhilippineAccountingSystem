<?php

declare(strict_types=1);

use App\Modules\Payroll\Presentation\Http\Controllers\ApprovePayrollRunController;
use App\Modules\Payroll\Presentation\Http\Controllers\ComputePayrollRunController;
use App\Modules\Payroll\Presentation\Http\Controllers\Generate13thMonthRunController;
use App\Modules\Payroll\Presentation\Http\Controllers\GenerateForm1601CController;
use App\Modules\Payroll\Presentation\Http\Controllers\GenerateForm2316Controller;
use App\Modules\Payroll\Presentation\Http\Controllers\GeneratePagIbigMcrfController;
use App\Modules\Payroll\Presentation\Http\Controllers\GeneratePhilHealthRf1Controller;
use App\Modules\Payroll\Presentation\Http\Controllers\GenerateSssR3Controller;
use App\Modules\Payroll\Presentation\Http\Controllers\CompensationPackageController;
use App\Modules\Payroll\Presentation\Http\Controllers\ComputeFinalPayRunController;
use App\Modules\Payroll\Presentation\Http\Controllers\LoanDeductionController;
use App\Modules\Payroll\Presentation\Http\Controllers\PayrollRunController;
use App\Modules\Payroll\Presentation\Http\Controllers\SettleLoanDeductionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('compensation-packages', CompensationPackageController::class);

    // Loan deductions (SSS salary loans / HDMF MPL & housing loans)
    Route::apiResource('loan-deductions', LoanDeductionController::class);
    Route::post('loan-deductions/{loanDeduction}/settle', SettleLoanDeductionController::class);

    // Resource (read-only ops)
    Route::apiResource('payroll-runs', PayrollRunController::class);

    // Single-action verbs
    Route::post('payroll-runs/final-pay/compute',    ComputeFinalPayRunController::class);
    Route::post('payroll-runs/compute',              ComputePayrollRunController::class);
    Route::post('payroll-runs/{run}/approve',        ApprovePayrollRunController::class)->middleware('mfa');
    Route::post('payroll-runs/13th-month/generate',  Generate13thMonthRunController::class)->middleware('mfa');

    // Payroll-driven BIR forms
    Route::post('payroll/forms/1601c/generate',  GenerateForm1601CController::class)->middleware('mfa');
    Route::post('payroll/forms/2316/generate',   GenerateForm2316Controller::class)->middleware('mfa');

    // Statutory remittance files (SSS / PhilHealth / Pag-IBIG)
    Route::post('payroll/remittances/sss-r3/generate',         GenerateSssR3Controller::class)->middleware('mfa');
    Route::post('payroll/remittances/philhealth-rf1/generate', GeneratePhilHealthRf1Controller::class)->middleware('mfa');
    Route::post('payroll/remittances/pagibig-mcrf/generate',   GeneratePagIbigMcrfController::class)->middleware('mfa');
});
