<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Tax\Application\Contracts\Form2307ReceivedRepositoryContract;
use App\Modules\Tax\Application\DTOs\Form2307ReceivedInput;
use App\Modules\Tax\Application\Exceptions\DuplicateForm2307ReceivedException;
use App\Modules\Tax\Domain\Entities\Form2307Received;
use App\Modules\Tax\Domain\Services\Form2307ReceivedValidator;
use App\Modules\Tax\Domain\ValueObjects\Form2307ReceivedId;

/**
 * Imports a single Form 2307 received from a customer/payor.
 *
 * Entry vectors that funnel into this Action:
 *   - HTTP POST /api/v1/tax/form-2307-received  (operator key-in)
 *   - PDF upload Listener (after OCR / template-extract)
 *   - CSV bulk loader
 *
 * Idempotency: hard-rejects duplicate (payor TIN + period + ATC + cert#)
 * because each cert represents one BIR-witnessed remittance and double-claiming
 * is an audit-flagged offence.
 */
final readonly class ImportForm2307Received
{
    public function __construct(
        private Form2307ReceivedRepositoryContract $repo,
        private Form2307ReceivedValidator $validator,
        private AuditWriterContract $audit,
    ) {
    }

    public function execute(Form2307ReceivedInput $input): Form2307Received
    {
        if ($this->repo->existsDuplicate(
            $input->companyId,
            $input->payorTin,
            $input->periodFrom,
            $input->periodTo,
            $input->atcCode,
            $input->certificateNo,
        )) {
            throw new DuplicateForm2307ReceivedException(sprintf(
                "A 2307 from %s for %s–%s (ATC %s, cert %s) is already on file. "
                ."Reject the existing record first if you're booking a correction.",
                $input->payorTin,
                $input->periodFrom->format('Y-m-d'),
                $input->periodTo->format('Y-m-d'),
                $input->atcCode,
                $input->certificateNo ?? '(none)',
            ));
        }

        $cert = new Form2307Received(
            id:                   Form2307ReceivedId::generate(),
            companyId:            $input->companyId,
            customerId:           $input->customerId,
            payorTin:             $input->payorTin,
            payorRegisteredName:  $input->payorRegisteredName,
            payorBranchCode:      $input->payorBranchCode,
            payorAddress:         $input->payorAddress,
            certificateNo:        $input->certificateNo,
            atcCode:              $input->atcCode,
            periodFrom:           $input->periodFrom,
            periodTo:             $input->periodTo,
            incomePayment:        $input->incomePayment,
            taxWithheld:          $input->taxWithheld,
            sourcePdfPath:        $input->sourcePdfPath,
            entryMethod:          $input->entryMethod,
            journalEntryId:       $input->journalEntryId,
            recordedBy:           $input->recordedBy,
        );

        $this->validator->validate($cert);
        $this->repo->save($cert);

        $this->audit->writeEvent(
            actorId:     $input->recordedBy,
            companyId:   $input->companyId,
            eventType:   'form2307received.recorded',
            aggregate:   'Form2307Received',
            aggregateId: $cert->id->value,
            payload: [
                'payor_tin'      => $cert->payorTin,
                'atc_code'       => $cert->atcCode,
                'period_from'    => $cert->periodFrom->format('Y-m-d'),
                'period_to'      => $cert->periodTo->format('Y-m-d'),
                'income_payment' => $cert->incomePayment,
                'tax_withheld'   => $cert->taxWithheld,
                'entry_method'   => $cert->entryMethod,
            ],
        );

        return $cert;
    }
}
