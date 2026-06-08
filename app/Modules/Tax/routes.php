<?php

declare(strict_types=1);

use App\Modules\Tax\Presentation\Http\Controllers\BirFormController;
use App\Modules\Tax\Presentation\Http\Controllers\BulkImportForm2307ReceivedController;
use App\Modules\Tax\Presentation\Http\Controllers\ExportAlphalistDatController;
use App\Modules\Tax\Presentation\Http\Controllers\ExportSawtDatController;
use App\Modules\Tax\Presentation\Http\Controllers\FileBirFormController;
use App\Modules\Tax\Presentation\Http\Controllers\Form2307ReceivedController;
use App\Modules\Tax\Presentation\Http\Controllers\GenerateForm1601EQController;
use App\Modules\Tax\Presentation\Http\Controllers\GenerateForm1604CFController;
use App\Modules\Tax\Presentation\Http\Controllers\GenerateForm1604EController;
use App\Modules\Tax\Presentation\Http\Controllers\GenerateForm1701Controller;
use App\Modules\Tax\Presentation\Http\Controllers\GenerateForm1701QController;
use App\Modules\Tax\Presentation\Http\Controllers\GenerateForm1702QController;
use App\Modules\Tax\Presentation\Http\Controllers\GenerateForm1702RTController;
use App\Modules\Tax\Presentation\Http\Controllers\GenerateForm2307PdfController;
use App\Modules\Tax\Presentation\Http\Controllers\GenerateForm2550MController;
use App\Modules\Tax\Presentation\Http\Controllers\GenerateForm2550QController;
use App\Modules\Tax\Presentation\Http\Controllers\OsdElectionController;
use App\Modules\Tax\Presentation\Http\Controllers\RejectForm2307ReceivedController;
use App\Modules\Tax\Presentation\Http\Controllers\SupersedeOsdElectionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tax module routes — wired by ModuleServiceProvider under /api/v1
|--------------------------------------------------------------------------
|
| Convention: each BIR form gets its own single-action generator.
| Resource controller covers list/show only.
|
*/

Route::middleware('auth:sanctum')->group(function () {
    // Resource: BIR forms list/show (read-only — generation is via single-actions)
    Route::apiResource('tax-forms', BirFormController::class);

    // Form generators (single-action)
    Route::post('tax-forms/2550m/generate',   GenerateForm2550MController::class)->middleware('mfa');
    Route::post('tax-forms/2550q/generate',   GenerateForm2550QController::class)->middleware('mfa');
    Route::post('tax-forms/1601eq/generate',  GenerateForm1601EQController::class)->middleware('mfa');

    // Quarterly ITR (Q1: May-15 individual / May-30 corp; Q2/Q3 90 days later)
    Route::post('tax-forms/1701q/generate',   GenerateForm1701QController::class)->middleware('mfa');
    Route::post('tax-forms/1702q/generate',   GenerateForm1702QController::class)->middleware('mfa');

    // Annual ITR (Apr-15 deadline for calendar filers)
    Route::post('tax-forms/1702rt/generate',  GenerateForm1702RTController::class)->middleware('mfa');
    Route::post('tax-forms/1701/generate',    GenerateForm1701Controller::class)->middleware('mfa');

    // Annual Alphalists (Jan-31 / Mar-1 deadlines)
    Route::post('tax-forms/1604cf/generate',  GenerateForm1604CFController::class)->middleware('mfa');
    Route::post('tax-forms/1604e/generate',   GenerateForm1604EController::class)->middleware('mfa');

    // 2307 PDF rendering
    Route::post('tax-forms/2307/{form2307}/render', GenerateForm2307PdfController::class);

    // DAT attachments
    Route::post('tax-forms/{form}/export-sawt',      ExportSawtDatController::class)->middleware('mfa');
    Route::post('tax-forms/{form}/export-alphalist', ExportAlphalistDatController::class)->middleware('mfa');

    // Mark a form as filed (with eBIRForms / EFPS reference)
    Route::post('tax-forms/{form}/file', FileBirFormController::class)->middleware('mfa');

    // 2307 certificates received from customers — feed 1701/1702 tax credits + SAWT
    Route::apiResource('tax/form-2307-received', Form2307ReceivedController::class);
    Route::post(
        'tax/form-2307-received/bulk-import',
        BulkImportForm2307ReceivedController::class,
    );
    Route::post(
        'tax/form-2307-received/{form}/reject',
        RejectForm2307ReceivedController::class,
    );

    // OSD elections — system-managed lock table for the year's deduction regime.
    // Read-only resource + a single supersede verb behind MFA (BIR-amendment workflow).
    Route::apiResource('tax/osd-elections', OsdElectionController::class)
        ->only(['index', 'show', 'create', 'edit', 'store', 'update', 'destroy']);
    Route::post(
        'tax/osd-elections/{election}/supersede',
        SupersedeOsdElectionController::class,
    )->middleware('mfa');
});
