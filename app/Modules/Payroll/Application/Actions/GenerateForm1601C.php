<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Tax\Application\Contracts\BirFormRepositoryContract;
use App\Modules\Tax\Application\Contracts\PdfRendererContract;
use App\Modules\Tax\Domain\Entities\BirForm;
use App\Modules\Tax\Domain\Entities\BirFormLine;
use App\Modules\Tax\Domain\Events\BirFormGenerated;
use App\Modules\Tax\Domain\ValueObjects\BirFormId;
use App\Modules\Tax\Domain\ValueObjects\FormPeriod;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Generates BIR Form 1601-C (Monthly Compensation Withholding Tax Return).
 *
 * Aggregates payroll.payslips for the month and produces the form.
 * Filed monthly; deadline is the 10th of the following month.
 */
final readonly class GenerateForm1601C
{
    public function __construct(
        private BirFormRepositoryContract $forms,
        private PdfRendererContract $pdf,
        private AuditWriterContract $audit,
        private Dispatcher $events,
    ) {
    }

    public function execute(string $companyId, int $year, int $month, string $actorId): BirForm
    {
        $period = FormPeriod::month($year, $month);

        return DB::transaction(function () use ($companyId, $period, $actorId) {
            $existing = $this->forms->findByPeriod($companyId, '1601C', $period);
            if ($existing !== null && $existing->status !== 'draft') {
                return $existing;
            }

            $totals = $this->aggregate($companyId, $period);

            $form = $existing ?? new BirForm(
                id:        BirFormId::generate(),
                companyId: $companyId,
                formType:  '1601C',
                period:    $period,
            );
            $form->lines = [];

            $form->addLine(new BirFormLine('14', 'Total Compensation',                $totals['total_comp']));
            $form->addLine(new BirFormLine('15', 'Non-Taxable Compensation',          $totals['non_taxable']));
            $form->addLine(new BirFormLine('16', 'Taxable Compensation (14 − 15)',    $totals['taxable']));
            $form->addLine(new BirFormLine('17', 'Total Tax Withheld',                $totals['wt_total']));
            $form->addLine(new BirFormLine('18', 'Less: Tax Remitted Previously',     '0.00'));
            $form->addLine(new BirFormLine('19', 'Tax Still Due / (Overremittance)',  $totals['wt_total']));

            $form->markGenerated(taxDue: $totals['wt_total']);
            $form->data = ['aggregates' => $totals];

            $this->forms->save($form);
            $form->pdfPath = $this->pdf->render($form);
            $this->forms->save($form);

            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $companyId,
                eventType:   'birform.generated',
                aggregate:   'BirForm',
                aggregateId: $form->id->value,
                payload: [
                    'form_type' => '1601C',
                    'period'    => $period->label(),
                    'tax_due'   => $totals['wt_total'],
                ],
            );

            $this->events->dispatch(new BirFormGenerated(
                birFormId:   $form->id->value,
                companyId:   $companyId,
                formType:    '1601C',
                periodFrom:  $period->from,
                periodTo:    $period->to,
                taxDue:      $totals['wt_total'],
                generatedBy: $actorId,
            ));

            return $form;
        });
    }

    /**
     * @return array{total_comp: string, non_taxable: string, taxable: string, wt_total: string, payslip_count: int}
     */
    private function aggregate(string $companyId, FormPeriod $period): array
    {
        $row = DB::selectOne(<<<'SQL'
            SELECT
                COALESCE(SUM(p.gross_compensation),       0) AS total_comp,
                COALESCE(SUM(p.nontaxable_compensation),  0) AS non_taxable,
                COALESCE(SUM(p.taxable_compensation),     0) AS taxable,
                COALESCE(SUM(p.withholding_tax),          0) AS wt_total,
                COUNT(*)                                       AS payslip_count
            FROM payroll.payslips p
            INNER JOIN payroll.payroll_runs r ON r.id = p.payroll_run_id
            INNER JOIN payroll.payroll_periods pp ON pp.id = r.payroll_period_id
            WHERE pp.company_id = ?::uuid
              AND r.approved_at IS NOT NULL
              AND pp.period_start >= ?::date
              AND pp.period_end   <= ?::date
        SQL, [$companyId, $period->from->format('Y-m-d'), $period->to->format('Y-m-d')]);

        return [
            'total_comp'    => (string) $row->total_comp,
            'non_taxable'   => (string) $row->non_taxable,
            'taxable'       => (string) $row->taxable,
            'wt_total'      => (string) $row->wt_total,
            'payslip_count' => (int) $row->payslip_count,
        ];
    }
}
