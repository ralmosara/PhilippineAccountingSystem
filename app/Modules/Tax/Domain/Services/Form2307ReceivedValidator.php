<?php

declare(strict_types=1);

namespace App\Modules\Tax\Domain\Services;

use App\Modules\Tax\Domain\Entities\Form2307Received;
use App\Modules\Tax\Domain\Exceptions\InvalidPayorTinException;
use DomainException;

/**
 * Domain invariants that must hold for every 2307 we accept into the system.
 *
 * Application-layer validation (FormRequest) is the first gate; this is the
 * **second** gate that runs even on imports / API calls that bypass HTTP
 * (CSV bulk loader, in-process tests). BIR's own validator at filing time
 * is the third — but the cheaper failures should happen here.
 */
final readonly class Form2307ReceivedValidator
{
    public function validate(Form2307Received $cert): void
    {
        $this->assertTinFormat($cert->payorTin);
        $this->assertReasonableWithholdingRate($cert);
        $this->assertPeriodWithinOneFiscalYear($cert);
    }

    /**
     * BIR TIN: 12 digits (or 9 + 3-digit branch). Accepts both
     * dashed ("000-123-456-000") and unformatted ("000123456000") forms.
     */
    private function assertTinFormat(string $tin): void
    {
        $digits = preg_replace('/\D/', '', $tin) ?? '';

        if (! in_array(strlen($digits), [9, 12], true)) {
            throw new InvalidPayorTinException(
                "Payor TIN must be 9 or 12 digits; got '{$tin}' ("
                .strlen($digits).' digits).'
            );
        }
    }

    /**
     * Catches obvious data-entry errors. Real BIR withholding rates per ATC
     * range from 0.5% to 30%. Anything above 35% signals a misplaced decimal
     * or wrong column on the input form.
     *
     * We don't enforce *exact* rate-per-ATC here because:
     *   1. ATCs change with BIR issuances; pinning here means another deploy.
     *   2. ATC-rate enforcement is the AtcCodeProvider's job at the
     *      withholding-calculation step, not at certificate-receipt time.
     */
    private function assertReasonableWithholdingRate(Form2307Received $cert): void
    {
        if (bccomp($cert->incomePayment, '0', 2) === 0) {
            // Zero-income certs are nonsensical but we let them through with a 0-tax row.
            // Aggregation just sees 0 + 0.
            return;
        }

        // rate = taxWithheld / incomePayment, computed at 4dp
        $rate = bcdiv($cert->taxWithheld, $cert->incomePayment, 4);

        if (bccomp($rate, '0.3500', 4) > 0) {
            throw new DomainException(
                "Implausible withholding rate {$rate} (tax {$cert->taxWithheld} on "
                ."income {$cert->incomePayment}). Highest BIR ATC rate is 30%. "
                ."Check the certificate columns."
            );
        }
    }

    /**
     * 2307s span at most one quarter in standard practice; we accept up to
     * one fiscal year (a payor that batched the year on a single cert is rare
     * but legal). Cross-year spans must be split into two certs.
     */
    private function assertPeriodWithinOneFiscalYear(Form2307Received $cert): void
    {
        $fromYear = (int) $cert->periodFrom->format('Y');
        $toYear   = (int) $cert->periodTo->format('Y');

        if ($fromYear !== $toYear) {
            throw new DomainException(
                "2307 period spans fiscal years ({$fromYear} → {$toYear}). "
                ."Split into separate certificates per BIR practice."
            );
        }
    }
}
