<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Modules\Accounting\Application\Actions\CreateJournalEntry;
use App\Modules\Accounting\Application\Actions\PostJournalEntry;
use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Payroll\Application\Contracts\PayrollRunRepositoryContract;
use App\Modules\Payroll\Application\Exceptions\PayrollAlreadyApprovedException;
use App\Modules\Payroll\Application\Exceptions\PayrollRunNotFoundException;
use App\Modules\Payroll\Domain\Entities\PayrollRun;
use App\Modules\Payroll\Domain\Events\PayrollRunApproved;
use App\Modules\Payroll\Domain\ValueObjects\PayrollRunId;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Approves a computed payroll run, posts the consolidated JV, and emits
 * PayrollRunApproved for downstream subscribers (statutory remittance
 * generation, payslip distribution, etc.).
 *
 * The consolidated JV:
 *   DR  Salaries and Wages              (total gross)
 *   DR  Employer SSS/PHIC/HDMF          (er contributions)
 *   CR  SSS Payable                     (ee + er total)
 *   CR  PhilHealth Payable
 *   CR  Pag-IBIG Payable
 *   CR  Withholding Tax Payable          (compensation WT)
 *   CR  Salaries Payable / Cash         (net pay total)
 */
final readonly class ApprovePayrollRun
{
    public function __construct(
        private PayrollRunRepositoryContract $runs,
        private CreateJournalEntry $createJournal,
        private PostJournalEntry $postJournal,
        private AuditWriterContract $audit,
        private Dispatcher $events,
    ) {
    }

    /**
     * @param  array{
     *     salaries_expense_account_id: string,
     *     employer_contributions_account_id: string,
     *     sss_payable_account_id: string,
     *     phic_payable_account_id: string,
     *     hdmf_payable_account_id: string,
     *     wt_compensation_payable_account_id: string,
     *     salaries_payable_account_id: string,
     *     jv_document_series_id: string,
     * }  $accounts
     */
    public function execute(
        string $payrollRunId,
        string $companyId,
        array $accounts,
        string $actorId,
        ?DateTimeImmutable $entryDate = null,
    ): PayrollRun {
        $run = $this->runs->findById(new PayrollRunId($payrollRunId))
            ?? throw new PayrollRunNotFoundException($payrollRunId);

        if ($run->status === 'approved' || $run->status === 'paid') {
            throw new PayrollAlreadyApprovedException($run->runNo);
        }

        $entryDate ??= new DateTimeImmutable();

        return DB::transaction(function () use ($run, $companyId, $accounts, $actorId, $entryDate) {
            // 1. Aggregate totals across all payslips
            $totals = $this->aggregate($run);

            // 2. Build + post JV
            $jeLines = $this->buildJournalLines($totals, $accounts);

            $journalEntry = $this->createJournal->execute(
                companyId:        $companyId,
                documentSeriesId: $accounts['jv_document_series_id'],
                entryDate:        $entryDate,
                lines:            $jeLines,
                source:           'payroll',
                sourceDocId:      $run->id->value,
                sourceDocType:    'PayrollRun',
                memo:             "Payroll {$run->runNo} ({$run->runType})",
                actorId:          $actorId,
            );

            $this->postJournal->execute(
                journalEntryId: $journalEntry->id->value,
                actorId:        $actorId,
            );

            // 3. Mark approved
            $run->approve($actorId);
            $run->journalEntryId = $journalEntry->id->value;
            $this->runs->save($run);

            // 4. Audit + event
            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $companyId,
                eventType:   'payrollrun.approved',
                aggregate:   'PayrollRun',
                aggregateId: $run->id->value,
                payload: [
                    'run_no'              => $run->runNo,
                    'journal_entry_id'    => $journalEntry->id->value,
                    'total_gross'         => $totals['gross'],
                    'total_net_pay'       => $totals['net_pay'],
                    'total_withholding'   => $totals['wt_comp'],
                ],
            );

            $this->events->dispatch(new PayrollRunApproved(
                payrollRunId:        $run->id->value,
                companyId:           $companyId,
                runNo:               $run->runNo,
                journalEntryId:      $journalEntry->id->value,
                approvedAt:          $run->approvedAt,
                approvedBy:          $actorId,
                totalNetPay:         $totals['net_pay'],
                totalWithholdingTax: $totals['wt_comp'],
            ));

            return $run;
        });
    }

    /**
     * @return array{
     *     gross: string,
     *     sss_ee: string, sss_er: string,
     *     phic_ee: string, phic_er: string,
     *     hdmf_ee: string, hdmf_er: string,
     *     wt_comp: string,
     *     net_pay: string,
     * }
     */
    private function aggregate(PayrollRun $run): array
    {
        $sums = [
            'gross'   => '0', 'sss_ee'  => '0', 'sss_er'  => '0',
            'phic_ee' => '0', 'phic_er' => '0', 'hdmf_ee' => '0',
            'hdmf_er' => '0', 'wt_comp' => '0', 'net_pay' => '0',
        ];

        foreach ($run->payslips as $p) {
            $sums['gross']   = bcadd($sums['gross'],   $p->grossCompensation->toPhp(), 2);
            $sums['sss_ee']  = bcadd($sums['sss_ee'],  $p->sssEe->toPhp(),  2);
            $sums['sss_er']  = bcadd($sums['sss_er'],  $p->sssEr->toPhp(),  2);
            $sums['phic_ee'] = bcadd($sums['phic_ee'], $p->phicEe->toPhp(), 2);
            $sums['phic_er'] = bcadd($sums['phic_er'], $p->phicEr->toPhp(), 2);
            $sums['hdmf_ee'] = bcadd($sums['hdmf_ee'], $p->hdmfEe->toPhp(), 2);
            $sums['hdmf_er'] = bcadd($sums['hdmf_er'], $p->hdmfEr->toPhp(), 2);
            $sums['wt_comp'] = bcadd($sums['wt_comp'], $p->withholdingTax->toPhp(), 2);
            $sums['net_pay'] = bcadd($sums['net_pay'], $p->netPay->toPhp(), 2);
        }

        return $sums;
    }

    /**
     * @param  array<string, string>  $t
     * @param  array<string, string>  $a
     * @return list<array<string, mixed>>
     */
    private function buildJournalLines(array $t, array $a): array
    {
        $lines = [];

        // DR Salaries Expense (gross)
        $lines[] = ['account_id' => $a['salaries_expense_account_id'], 'debit' => $t['gross'],
                    'php_amount' => $t['gross']];

        // DR Employer Contributions (sum of er)
        $erTotal = bcadd(bcadd($t['sss_er'], $t['phic_er'], 2), $t['hdmf_er'], 2);
        if (bccomp($erTotal, '0', 2) > 0) {
            $lines[] = ['account_id' => $a['employer_contributions_account_id'], 'debit' => $erTotal,
                        'php_amount' => $erTotal];
        }

        // CR statutory payables (ee + er)
        $sssTotal = bcadd($t['sss_ee'], $t['sss_er'], 2);
        $phicTotal = bcadd($t['phic_ee'], $t['phic_er'], 2);
        $hdmfTotal = bcadd($t['hdmf_ee'], $t['hdmf_er'], 2);

        foreach ([
            ['account' => $a['sss_payable_account_id'],  'amount' => $sssTotal],
            ['account' => $a['phic_payable_account_id'], 'amount' => $phicTotal],
            ['account' => $a['hdmf_payable_account_id'], 'amount' => $hdmfTotal],
            ['account' => $a['wt_compensation_payable_account_id'], 'amount' => $t['wt_comp']],
        ] as $row) {
            if (bccomp($row['amount'], '0', 2) > 0) {
                $lines[] = ['account_id' => $row['account'], 'credit' => $row['amount'],
                            'php_amount' => bcmul($row['amount'], '-1', 2)];
            }
        }

        // CR Salaries Payable (net pay)
        $lines[] = ['account_id' => $a['salaries_payable_account_id'], 'credit' => $t['net_pay'],
                    'php_amount' => bcmul($t['net_pay'], '-1', 2)];

        return $lines;
    }
}
