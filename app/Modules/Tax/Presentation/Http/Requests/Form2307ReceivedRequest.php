<?php

declare(strict_types=1);

namespace App\Modules\Tax\Presentation\Http\Requests;

use App\Modules\Tax\Application\DTOs\Form2307ReceivedInput;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class Form2307ReceivedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tax.form_2307_received.write') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_id'           => ['nullable', 'uuid'],
            'payor_tin'             => ['required', 'string', 'min:9', 'max:16',
                                         'regex:/^[0-9\-]+$/'],
            'payor_registered_name' => ['required', 'string', 'max:255'],
            'payor_branch_code'     => ['nullable', 'string', 'max:8'],
            'payor_address'         => ['nullable', 'string', 'max:500'],
            'certificate_no'        => ['nullable', 'string', 'max:64'],
            'atc_code'              => ['required', 'string', 'max:16'],
            'period_from'           => ['required', 'date'],
            'period_to'              => ['required', 'date', 'after_or_equal:period_from'],
            'income_payment'        => ['required', 'string', 'regex:/^\d+(\.\d{1,4})?$/'],
            'tax_withheld'          => ['required', 'string', 'regex:/^\d+(\.\d{1,4})?$/'],
            'source_pdf_path'       => ['nullable', 'string', 'max:500'],
            'entry_method'          => ['required', 'in:manual,pdf_upload,csv_import'],
            'journal_entry_id'      => ['nullable', 'uuid'],
        ];
    }

    public function toInput(): Form2307ReceivedInput
    {
        return new Form2307ReceivedInput(
            companyId:           (string) $this->user()->company_id,
            customerId:          $this->input('customer_id'),
            payorTin:            (string) $this->input('payor_tin'),
            payorRegisteredName: (string) $this->input('payor_registered_name'),
            payorBranchCode:     (string) $this->input('payor_branch_code', '000'),
            payorAddress:        $this->input('payor_address'),
            certificateNo:       $this->input('certificate_no'),
            atcCode:             (string) $this->input('atc_code'),
            periodFrom:          new DateTimeImmutable((string) $this->input('period_from')),
            periodTo:            new DateTimeImmutable((string) $this->input('period_to')),
            incomePayment:       (string) $this->input('income_payment'),
            taxWithheld:         (string) $this->input('tax_withheld'),
            sourcePdfPath:       $this->input('source_pdf_path'),
            entryMethod:         (string) $this->input('entry_method', 'manual'),
            journalEntryId:      $this->input('journal_entry_id'),
            recordedBy:          (string) $this->user()->id,
        );
    }
}
