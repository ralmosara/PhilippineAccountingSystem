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
 * Exports the SAWT (Summary Alphalist of Withholding Taxes) DAT file
 * for a generated 1601-EQ form.
 *
 * The DAT is the BIR-mandated fixed-format text attachment to 1601-EQ.
 * It is uploaded separately into eBIRForms when filing the quarterly
 * remittance.
 */
final readonly class ExportSawtDat
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

        if ($form->formType !== '1601EQ') {
            throw new \InvalidArgumentException(
                "SAWT DAT is only valid for 1601-EQ; this form is {$form->formType}."
            );
        }

        $company = DB::table('identity.companies')
            ->where('id', $form->companyId)
            ->first();

        $datContent = $this->formatter->formatSawt(
            entries: $form->alphalistEntries,
            header: [
                'tin'              => $company->tin ?? '000000000000',
                'registered_name'  => $company->registered_name ?? 'UNKNOWN',
                'period_from'      => $form->period->from->format('Y-m-d'),
                'period_to'        => $form->period->to->format('Y-m-d'),
            ],
        );

        $path = sprintf(
            'bir/%s/%d/sawt/SAWT_%s_%s.dat',
            $form->companyId,
            $form->period->year,
            $form->period->label(),
            substr($birFormId, 0, 8),
        );

        Storage::put($path, $datContent);

        $form->datPath = $path;
        $this->forms->save($form);

        $this->audit->writeEvent(
            actorId:     $actorId,
            companyId:   $form->companyId,
            eventType:   'birform.sawt_exported',
            aggregate:   'BirForm',
            aggregateId: $form->id->value,
            payload:     ['dat_path' => $path, 'entries' => count($form->alphalistEntries)],
        );

        return $path;
    }
}
