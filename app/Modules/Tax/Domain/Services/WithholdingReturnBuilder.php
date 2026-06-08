<?php

declare(strict_types=1);

namespace App\Modules\Tax\Domain\Services;

use App\Modules\Tax\Domain\Entities\AlphalistEntry;
use App\Modules\Tax\Domain\Entities\BirForm;
use App\Modules\Tax\Domain\Entities\BirFormLine;
use DateTimeImmutable;

/**
 * Builds BIR Form 1601-EQ (Quarterly Remittance of Creditable EWT) and
 * its SAWT (Summary Alphalist of Withholding Taxes) attachment.
 *
 * Source: tax.form_2307 rows for the period, grouped by ATC code.
 *
 * 1601-EQ key lines:
 *   13  — Total Tax Required to be Withheld and Remitted (sum of all 2307 tax_withheld)
 *   14  — Less: Tax Remitted in Return Previously Filed (for amendments)
 *   17  — Tax Still Due / (Overremittance)
 */
final readonly class WithholdingReturnBuilder
{
    /**
     * @param  list<array{
     *     vendor_bill_id: string,
     *     vendor_id: string,
     *     vendor_tin: string,
     *     vendor_name: string,
     *     atc_code: string,
     *     income_payment: string,
     *     tax_withheld: string,
     *     payment_date: string,
     * }>  $form2307Rows
     */
    public function build(BirForm $form, array $form2307Rows): BirForm
    {
        $totalIncome  = '0';
        $totalWithheld = '0';
        $byAtc = [];

        foreach ($form2307Rows as $row) {
            $totalIncome   = bcadd($totalIncome,  $row['income_payment'], 2);
            $totalWithheld = bcadd($totalWithheld, $row['tax_withheld'], 2);

            $atc = $row['atc_code'];
            if (! isset($byAtc[$atc])) {
                $byAtc[$atc] = ['income' => '0', 'withheld' => '0'];
            }
            $byAtc[$atc]['income']   = bcadd($byAtc[$atc]['income'],   $row['income_payment'], 2);
            $byAtc[$atc]['withheld'] = bcadd($byAtc[$atc]['withheld'], $row['tax_withheld'], 2);

            // Each 2307 becomes a SAWT entry
            $form->addAlphalistEntry(new AlphalistEntry(
                schedule:        'sawt',
                tin:             $row['vendor_tin'],
                registeredName:  $row['vendor_name'],
                atcCode:         $row['atc_code'],
                incomePayment:   $row['income_payment'],
                taxWithheld:     $row['tax_withheld'],
                paymentDate:     new DateTimeImmutable($row['payment_date']),
            ));
        }

        // 1601-EQ form lines
        $form->addLine(new BirFormLine('13', 'Total Tax Required to be Withheld and Remitted', $totalWithheld));
        $form->addLine(new BirFormLine('14', 'Less: Tax Remitted in Return Previously Filed', '0.00'));
        $form->addLine(new BirFormLine('17', 'Tax Still Due / (Overremittance)',               $totalWithheld));

        // Per-ATC breakdown lines (numbered B1, B2, ...)
        $i = 1;
        foreach ($byAtc as $atc => $totals) {
            $form->addLine(new BirFormLine(
                "B{$i}",
                "ATC {$atc} — Income payment / WT",
                $totals['withheld'],
                ['atc' => $atc, 'income' => $totals['income']],
            ));
            $i++;
        }

        $form->markGenerated(taxDue: $totalWithheld);
        $form->data = [
            'total_income_payment'    => $totalIncome,
            'total_tax_withheld'      => $totalWithheld,
            'by_atc'                  => $byAtc,
            'sawt_entries_count'      => count($form2307Rows),
        ];

        return $form;
    }
}
