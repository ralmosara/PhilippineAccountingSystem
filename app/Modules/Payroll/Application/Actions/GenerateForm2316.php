<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Tax\Application\Contracts\BirFormRepositoryContract;
use App\Modules\Tax\Application\Contracts\PdfRendererContract;
use App\Modules\Tax\Domain\Entities\BirForm;
use App\Modules\Tax\Domain\Entities\BirFormLine;
use App\Modules\Tax\Domain\ValueObjects\BirFormId;
use App\Modules\Tax\Domain\ValueObjects\FormPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Generates BIR Form 2316 (Annual Certificate of Compensation Payment /
 * Tax Withheld) for a specific employee for a specific year.
 *
 * One row in tax.bir_forms per (employee, year). The employee is
 * encoded in the form's data->>'employee_id' (the form doesn't have
 * its own employee_id column since most BIR forms aggregate at company level).
 *
 * Filing deadline: Jan 31 of the following year, given to the employee.
 */
final readonly class GenerateForm2316
{
    public function __construct(
        private BirFormRepositoryContract $forms,
        private PdfRendererContract $pdf,
        private AuditWriterContract $audit,
    ) {
    }

    public function execute(string $companyId, string $employeeId, int $year, string $actorId): BirForm
    {
        $period = FormPeriod::year($year);

        return DB::transaction(function () use ($companyId, $employeeId, $period, $actorId, $year) {
            // Per-employee aggregation
            $row = DB::selectOne(<<<'SQL'
                SELECT
                    COALESCE(SUM(p.gross_compensation),       0) AS total_comp,
                    COALESCE(SUM(p.nontaxable_compensation),  0) AS non_taxable,
                    COALESCE(SUM(p.taxable_compensation),     0) AS taxable,
                    COALESCE(SUM(p.withholding_tax),          0) AS wt_total,
                    COALESCE(SUM(p.sss_ee),                    0) AS sss_total,
                    COALESCE(SUM(p.phic_ee),                   0) AS phic_total,
                    COALESCE(SUM(p.hdmf_ee),                   0) AS hdmf_total
                FROM payroll.payslips p
                INNER JOIN payroll.payroll_runs r       ON r.id = p.payroll_run_id
                INNER JOIN payroll.payroll_periods pp   ON pp.id = r.payroll_period_id
                WHERE pp.company_id = ?::uuid
                  AND p.employee_id = ?::uuid
                  AND r.approved_at IS NOT NULL
                  AND pp.period_start >= ?::date
                  AND pp.period_end   <= ?::date
            SQL, [$companyId, $employeeId, "{$year}-01-01", "{$year}-12-31"]);

            $form = new BirForm(
                id:        BirFormId::generate(),
                companyId: $companyId,
                formType:  '2316',
                period:    $period,
            );

            $form->addLine(new BirFormLine('A.1',  'Total Compensation',                  (string) $row->total_comp));
            $form->addLine(new BirFormLine('A.2',  'Non-Taxable Compensation',            (string) $row->non_taxable));
            $form->addLine(new BirFormLine('A.3',  'Taxable Compensation',                (string) $row->taxable));
            $form->addLine(new BirFormLine('B.1',  'SSS Contribution',                    (string) $row->sss_total));
            $form->addLine(new BirFormLine('B.2',  'PhilHealth Contribution',             (string) $row->phic_total));
            $form->addLine(new BirFormLine('B.3',  'Pag-IBIG Contribution',               (string) $row->hdmf_total));
            $form->addLine(new BirFormLine('C',    'Total Tax Withheld',                  (string) $row->wt_total));

            $form->markGenerated(taxDue: (string) $row->wt_total);
            $form->data = [
                'employee_id' => $employeeId,
                'aggregates'  => (array) $row,
            ];

            $this->forms->save($form);
            $form->pdfPath = $this->pdf->render($form);
            $this->forms->save($form);

            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $companyId,
                eventType:   'form2316.generated',
                aggregate:   'BirForm',
                aggregateId: $form->id->value,
                payload: [
                    'employee_id'    => $employeeId,
                    'year'           => $year,
                    'total_comp'     => (string) $row->total_comp,
                    'wt_total'       => (string) $row->wt_total,
                ],
            );

            return $form;
        });
    }
}
