<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\DTOs;

/**
 * Aggregated tax-credit summary for a fiscal year, derived from received
 * 2307 certificates. Feeds:
 *   - 1701 line 60(D) "Tax Credits/Payments: Creditable Tax Withheld"
 *   - 1702-RT line 30 "Less: Creditable Tax Withheld"
 *   - SAWT quarterly attachment alphalist
 *   - 1701/1702 alphalist of payors
 *
 * @phpstan-type PayorRollup array{
 *     payor_tin: string,
 *     payor_name: string,
 *     atc_code: string,
 *     income_payment: string,
 *     tax_withheld: string,
 *     cert_count: int,
 * }
 */
final readonly class WithholdingCreditSummary
{
    /**
     * @param  string                  $companyId
     * @param  int                     $fiscalYear
     * @param  string                  $totalIncomePayment   sum of incomePayment across all claimable certs
     * @param  string                  $totalTaxWithheld     ← goes on the ITR as a credit
     * @param  list<string>            $claimableCertIds     IDs to flip to 'claimed' on filing
     * @param  list<PayorRollup>       $byPayor              for alphalist generation
     * @param  array<string, string>   $byAtc                ATC → summed tax_withheld (for diagnostic display)
     */
    public function __construct(
        public string $companyId,
        public int $fiscalYear,
        public string $totalIncomePayment,
        public string $totalTaxWithheld,
        public array $claimableCertIds,
        public array $byPayor,
        public array $byAtc,
    ) {
    }
}
