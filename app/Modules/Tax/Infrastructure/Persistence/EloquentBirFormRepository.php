<?php

declare(strict_types=1);

namespace App\Modules\Tax\Infrastructure\Persistence;

use App\Modules\Tax\Application\Contracts\BirFormRepositoryContract;
use App\Modules\Tax\Domain\Entities\AlphalistEntry;
use App\Modules\Tax\Domain\Entities\BirForm;
use App\Modules\Tax\Domain\Entities\BirFormLine;
use App\Modules\Tax\Domain\ValueObjects\BirFormId;
use App\Modules\Tax\Domain\ValueObjects\FormPeriod;
use App\Modules\Tax\Infrastructure\Persistence\Eloquent\AlphalistEntryModel;
use App\Modules\Tax\Infrastructure\Persistence\Eloquent\BirFormLineModel;
use App\Modules\Tax\Infrastructure\Persistence\Eloquent\BirFormModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

final class EloquentBirFormRepository implements BirFormRepositoryContract
{
    public function findById(BirFormId $id): ?BirForm
    {
        $model = BirFormModel::query()->with(['lines', 'alphalistEntries'])->find($id->value);
        return $model ? $this->toDomain($model) : null;
    }

    public function findByPeriod(string $companyId, string $formType, FormPeriod $period): ?BirForm
    {
        $model = BirFormModel::query()
            ->with(['lines', 'alphalistEntries'])
            ->where('company_id', $companyId)
            ->where('form_type', $formType)
            ->where('period_from', $period->from)
            ->where('period_to', $period->to)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function save(BirForm $form): void
    {
        DB::transaction(function () use ($form) {
            BirFormModel::query()->updateOrInsert(
                ['id' => $form->id->value],
                [
                    'company_id'     => $form->companyId,
                    'form_type'      => $form->formType,
                    'period_from'    => $form->period->from,
                    'period_to'      => $form->period->to,
                    'year'           => $form->period->year,
                    'quarter'        => $form->period->quarter,
                    'month'          => $form->period->month,
                    'data'           => json_encode($form->data, JSON_THROW_ON_ERROR),
                    'xml_path'       => $form->xmlPath,
                    'pdf_path'       => $form->pdfPath,
                    'dat_path'       => $form->datPath,
                    'generated_at'   => $form->generatedAt,
                    'filed_at'       => $form->filedAt,
                    'bir_filing_ref' => $form->birFilingRef,
                    'tax_due'        => $form->taxDue,
                    'tax_paid'       => $form->taxPaid,
                    'status'         => $form->status,
                    'updated_at'     => now(),
                    'created_at'     => now(),
                ],
            );

            // Replace lines + alphalist on each save (forms are recomputed, not patched)
            BirFormLineModel::query()->where('bir_form_id', $form->id->value)->delete();
            AlphalistEntryModel::query()->where('bir_form_id', $form->id->value)->delete();

            foreach ($form->lines as $line) {
                BirFormLineModel::query()->insert([
                    'id'          => Uuid::uuid4()->toString(),
                    'bir_form_id' => $form->id->value,
                    'line_code'   => $line->lineCode,
                    'description' => $line->description,
                    'amount'      => $line->amount,
                    'breakdown'   => json_encode($line->breakdown, JSON_THROW_ON_ERROR),
                    'updated_at'  => now(),
                    'created_at'  => now(),
                ]);
            }

            foreach ($form->alphalistEntries as $entry) {
                AlphalistEntryModel::query()->insert([
                    'id'                => Uuid::uuid4()->toString(),
                    'bir_form_id'       => $form->id->value,
                    'schedule'          => $entry->schedule,
                    'tin'               => $entry->tin,
                    'registered_name'   => $entry->registeredName,
                    'atc_code'          => $entry->atcCode,
                    'income_payment'    => $entry->incomePayment,
                    'tax_withheld'      => $entry->taxWithheld,
                    'tax_type'          => $entry->taxType,
                    'payment_date'      => $entry->paymentDate,
                    'updated_at'        => now(),
                    'created_at'        => now(),
                ]);
            }
        });
    }

    private function toDomain(BirFormModel $m): BirForm
    {
        $period = new FormPeriod(
            from:    new DateTimeImmutable($m->period_from->toIso8601String()),
            to:      new DateTimeImmutable($m->period_to->toIso8601String()),
            year:    (int) $m->year,
            month:   $m->month,
            quarter: $m->quarter,
        );

        $form = new BirForm(
            id:        new BirFormId($m->id),
            companyId: $m->company_id,
            formType:  $m->form_type,
            period:    $period,
        );

        $form->status        = $m->status;
        $form->taxDue        = (string) $m->tax_due;
        $form->taxPaid       = (string) $m->tax_paid;
        $form->data          = $m->data ?? [];
        $form->xmlPath       = $m->xml_path;
        $form->pdfPath       = $m->pdf_path;
        $form->datPath       = $m->dat_path;
        $form->birFilingRef  = $m->bir_filing_ref;
        $form->generatedAt   = $m->generated_at ? new DateTimeImmutable($m->generated_at->toIso8601String()) : null;
        $form->filedAt       = $m->filed_at ? new DateTimeImmutable($m->filed_at->toIso8601String()) : null;

        foreach ($m->lines as $line) {
            $form->lines[] = new BirFormLine(
                lineCode:    $line->line_code,
                description: $line->description,
                amount:      (string) $line->amount,
                breakdown:   $line->breakdown ?? [],
            );
        }

        foreach ($m->alphalistEntries as $entry) {
            $form->alphalistEntries[] = new AlphalistEntry(
                schedule:        $entry->schedule,
                tin:             $entry->tin,
                registeredName:  $entry->registered_name,
                atcCode:         $entry->atc_code,
                incomePayment:   (string) $entry->income_payment,
                taxWithheld:     (string) $entry->tax_withheld,
                taxType:         $entry->tax_type,
                paymentDate:     $entry->payment_date ? new DateTimeImmutable($entry->payment_date->toIso8601String()) : null,
            );
        }

        return $form;
    }
}
