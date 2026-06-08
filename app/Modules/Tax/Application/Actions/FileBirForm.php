<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Tax\Application\Contracts\BirFormRepositoryContract;
use App\Modules\Tax\Application\Exceptions\FormAlreadyFiledException;
use App\Modules\Tax\Application\Exceptions\FormNotFoundException;
use App\Modules\Tax\Domain\Events\BirFormFiled;
use App\Modules\Tax\Domain\ValueObjects\BirFormId;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Records that a BIR form has been filed (manually or via eBIRForms/EFPS).
 * The actual transmission to BIR is out-of-scope for this action; this
 * just captures the proof-of-filing reference number.
 */
final readonly class FileBirForm
{
    public function __construct(
        private BirFormRepositoryContract $forms,
        private AuditWriterContract $audit,
        private Dispatcher $events,
    ) {
    }

    public function execute(
        string $birFormId,
        string $birFilingRef,
        string $channel,                       // 'ebirforms_offline' | 'ebirforms_online' | 'efps' | 'manual'
        string $actorId,
    ): void {
        $form = $this->forms->findById(new BirFormId($birFormId))
            ?? throw new FormNotFoundException($birFormId);

        if ($form->status === 'filed') {
            throw new FormAlreadyFiledException($form->formType, $form->period->label());
        }

        DB::transaction(function () use ($form, $birFilingRef, $channel, $actorId) {
            $form->markFiled($birFilingRef);
            $this->forms->save($form);

            DB::table('tax.form_filing_log')->insert([
                'bir_form_id'  => $form->id->value,
                'attempted_at' => now(),
                'channel'      => $channel,
                'result'       => 'success',
                'ack_no'       => $birFilingRef,
            ]);

            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $form->companyId,
                eventType:   'birform.filed',
                aggregate:   'BirForm',
                aggregateId: $form->id->value,
                payload: [
                    'form_type'   => $form->formType,
                    'period'      => $form->period->label(),
                    'filing_ref'  => $birFilingRef,
                    'channel'     => $channel,
                ],
            );

            $this->events->dispatch(new BirFormFiled(
                birFormId:    $form->id->value,
                companyId:    $form->companyId,
                formType:     $form->formType,
                birFilingRef: $birFilingRef,
                channel:      $channel,
                filedAt:      $form->filedAt,
                filedBy:      $actorId,
            ));
        });
    }
}
