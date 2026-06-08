<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\Queries;

use App\Modules\Tax\Application\Contracts\Form2307ReceivedRepositoryContract;
use App\Modules\Tax\Application\DTOs\WithholdingCreditSummary;
use App\Modules\Tax\Domain\Entities\Form2307Received;
use DateTimeImmutable;

/**
 * Sums every 'recorded' (i.e. not-yet-claimed and not-rejected) 2307 received
 * during the given fiscal year into a single credit total. Caller passes the
 * result to GenerateForm1701 / GenerateForm1702RT.
 *
 * Read-side, no mutation. The "lock these certs as claimed" step happens
 * in the ITR generation action via repo->markClaimed(), inside the same
 * transaction that emits the BIR form.
 */
final readonly class AggregateWithholdingCreditsForYear
{
    public function __construct(
        private Form2307ReceivedRepositoryContract $repo,
    ) {
    }

    public function execute(string $companyId, int $fiscalYear): WithholdingCreditSummary
    {
        $from = new DateTimeImmutable("{$fiscalYear}-01-01");
        $to   = new DateTimeImmutable("{$fiscalYear}-12-31");

        $certs = $this->repo->findRecordedInPeriod($companyId, $from, $to);

        $totalIncome  = '0.00';
        $totalWithheld = '0.00';
        $claimableIds  = [];
        /** @var array<string, array{tin: string, name: string, atc: string, income: string, tax: string, count: int}> $payorMap */
        $payorMap = [];
        /** @var array<string, string> $atcMap */
        $atcMap   = [];

        foreach ($certs as $cert) {
            if (! $cert->isClaimable()) {
                continue;       // belt-and-braces in case the repo returns 'claimed' rows
            }

            $totalIncome   = bcadd($totalIncome,   $cert->incomePayment, 2);
            $totalWithheld = bcadd($totalWithheld, $cert->taxWithheld,   2);
            $claimableIds[] = $cert->id->value;

            // Group by payor+ATC so the alphalist can emit one row per (payor, ATC) pair.
            $key = $cert->payorTin.'|'.$cert->atcCode;
            if (! isset($payorMap[$key])) {
                $payorMap[$key] = [
                    'tin'    => $cert->payorTin,
                    'name'   => $cert->payorRegisteredName,
                    'atc'    => $cert->atcCode,
                    'income' => '0.00',
                    'tax'    => '0.00',
                    'count'  => 0,
                ];
            }
            $payorMap[$key]['income'] = bcadd($payorMap[$key]['income'], $cert->incomePayment, 2);
            $payorMap[$key]['tax']    = bcadd($payorMap[$key]['tax'],    $cert->taxWithheld,   2);
            $payorMap[$key]['count']++;

            $atcMap[$cert->atcCode] = bcadd($atcMap[$cert->atcCode] ?? '0.00', $cert->taxWithheld, 2);
        }

        $byPayor = array_values(array_map(
            fn (array $row) => [
                'payor_tin'      => $row['tin'],
                'payor_name'     => $row['name'],
                'atc_code'       => $row['atc'],
                'income_payment' => $row['income'],
                'tax_withheld'   => $row['tax'],
                'cert_count'     => $row['count'],
            ],
            $payorMap,
        ));

        return new WithholdingCreditSummary(
            companyId:          $companyId,
            fiscalYear:         $fiscalYear,
            totalIncomePayment: $totalIncome,
            totalTaxWithheld:   $totalWithheld,
            claimableCertIds:   $claimableIds,
            byPayor:            $byPayor,
            byAtc:              $atcMap,
        );
    }
}
