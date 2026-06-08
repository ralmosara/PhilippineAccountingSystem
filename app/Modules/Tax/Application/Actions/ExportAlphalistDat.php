<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Tax\Application\Contracts\BirFormRepositoryContract;
use App\Modules\Tax\Application\Exceptions\FormNotFoundException;
use App\Modules\Tax\Domain\Services\DatFileFormatter;
use App\Modules\Tax\Domain\ValueObjects\BirFormId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Exports the BIR DAT alphalist attachment for an annual return:
 *   - 1604-CF: Schedule 7.1 (MWE) + 7.2 (non-MWE) employee alphalist
 *   - 1604-E:  Schedule 1 payee alphalist (EWT)
 *
 * Generic — accepts any BirForm whose status is 'generated' and whose
 * alphalist_entries are populated. Stores the .dat file in MinIO and
 * sets `form.dat_path`.
 */
final readonly class ExportAlphalistDat
{
    public function __construct(
        private BirFormRepositoryContract $forms,
        private DatFileFormatter $formatter,
        private AuditWriterContract $audit,
    ) {
    }

    public function execute(string $birFormId, string $actorId): string
    {
        $form = $this->forms->findById(new BirFormId($birFormId))
            ?? throw new FormNotFoundException($birFormId);

        if (! in_array($form->formType, ['1604CF', '1604E', '1604F'], true)) {
            throw new \InvalidArgumentException(
                "Alphalist DAT export is only valid for 1604-CF / 1604-E / 1604-F; this form is {$form->formType}."
            );
        }

        $company = DB::table('identity.companies')->where('id', $form->companyId)->first();

        $datContent = $this->formatter->formatAnnualAlphalist(
            entries: $form->alphalistEntries,
            header: [
                'tin'             => $company->tin ?? '000000000000',
                'registered_name' => $company->registered_name ?? 'UNKNOWN',
                'year'            => $form->period->year,
            ],
        );

        $path = sprintf(
            'bir/%s/%d/%s/%s_%d_%s.dat',
            $form->companyId,
            $form->period->year,
            strtolower($form->formType),
            $form->formType,
            $form->period->year,
            substr($birFormId, 0, 8),
        );

        Storage::put($path, $datContent);

        $form->datPath = $path;
        $this->forms->save($form);

        $this->audit->writeEvent(
            actorId:     $actorId,
            companyId:   $form->companyId,
            eventType:   'birform.alphalist_exported',
            aggregate:   'BirForm',
            aggregateId: $form->id->value,
            payload: [
                'form_type' => $form->formType,
                'dat_path'  => $path,
                'entries'   => count($form->alphalistEntries),
            ],
        );

        return $path;
    }
}
