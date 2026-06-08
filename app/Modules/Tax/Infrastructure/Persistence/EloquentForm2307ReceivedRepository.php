<?php

declare(strict_types=1);

namespace App\Modules\Tax\Infrastructure\Persistence;

use App\Modules\Tax\Application\Contracts\Form2307ReceivedRepositoryContract;
use App\Modules\Tax\Domain\Entities\Form2307Received;
use App\Modules\Tax\Domain\ValueObjects\Form2307ReceivedId;
use App\Modules\Tax\Infrastructure\Persistence\Eloquent\Form2307ReceivedModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentForm2307ReceivedRepository implements Form2307ReceivedRepositoryContract
{
    public function findById(Form2307ReceivedId $id): ?Form2307Received
    {
        $model = Form2307ReceivedModel::query()->find($id->value);
        return $model ? $this->toDomain($model) : null;
    }

    public function save(Form2307Received $cert): void
    {
        Form2307ReceivedModel::query()->updateOrInsert(
            ['id' => $cert->id->value],
            [
                'company_id'             => $cert->companyId,
                'customer_id'            => $cert->customerId,
                'payor_tin'              => $cert->payorTin,
                'payor_registered_name'  => $cert->payorRegisteredName,
                'payor_branch_code'      => $cert->payorBranchCode,
                'payor_address'          => $cert->payorAddress,
                'certificate_no'         => $cert->certificateNo,
                'atc_code'               => $cert->atcCode,
                'period_from'            => $cert->periodFrom,
                'period_to'              => $cert->periodTo,
                'income_payment'         => $cert->incomePayment,
                'tax_withheld'           => $cert->taxWithheld,
                'source_pdf_path'        => $cert->sourcePdfPath,
                'entry_method'           => $cert->entryMethod,
                'status'                 => $cert->status,
                'claimed_in_bir_form_id' => $cert->claimedInBirFormId,
                'rejection_reason'       => $cert->rejectionReason,
                'journal_entry_id'       => $cert->journalEntryId,
                'recorded_by'            => $cert->recordedBy,
                'updated_at'             => now(),
                'created_at'             => now(),
            ],
        );
    }

    public function delete(Form2307ReceivedId $id): void
    {
        Form2307ReceivedModel::query()->where('id', $id->value)->delete();
    }

    public function findRecordedInPeriod(
        string $companyId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): array {
        $models = Form2307ReceivedModel::query()
            ->where('company_id', $companyId)
            ->where('status', 'recorded')
            ->whereBetween('period_to', [$from, $to])
            ->orderBy('period_to')
            ->orderBy('payor_tin')
            ->get();

        return $models->map(fn ($m) => $this->toDomain($m))->all();
    }

    public function markClaimed(array $ids, string $birFormId): void
    {
        if ($ids === []) {
            return;
        }

        $idValues = array_map(static fn ($id) => $id->value, $ids);

        DB::transaction(function () use ($idValues, $birFormId) {
            // Defensive: refuse to flip rows that have already been claimed
            // (would mask a double-claim bug at the call site).
            $alreadyClaimed = Form2307ReceivedModel::query()
                ->whereIn('id', $idValues)
                ->where('status', 'claimed')
                ->where('claimed_in_bir_form_id', '!=', $birFormId)
                ->pluck('id')
                ->all();

            if ($alreadyClaimed !== []) {
                throw new \RuntimeException(
                    'Refusing to re-claim already-claimed certs: '.implode(', ', $alreadyClaimed),
                );
            }

            Form2307ReceivedModel::query()
                ->whereIn('id', $idValues)
                ->where('status', 'recorded')
                ->update([
                    'status'                 => 'claimed',
                    'claimed_in_bir_form_id' => $birFormId,
                    'updated_at'             => now(),
                ]);
        });
    }

    public function existsDuplicate(
        string $companyId,
        string $payorTin,
        DateTimeImmutable $periodFrom,
        DateTimeImmutable $periodTo,
        string $atcCode,
        ?string $certificateNo,
    ): bool {
        $q = Form2307ReceivedModel::query()
            ->where('company_id', $companyId)
            ->where('payor_tin', $payorTin)
            ->where('period_from', $periodFrom)
            ->where('period_to', $periodTo)
            ->where('atc_code', $atcCode)
            ->where('status', '!=', 'rejected');

        // null vs '' must collide too — pass either as a duplicate of a blank cert#
        if ($certificateNo === null || $certificateNo === '') {
            $q->where(function ($w) {
                $w->whereNull('certificate_no')->orWhere('certificate_no', '');
            });
        } else {
            $q->where('certificate_no', $certificateNo);
        }

        return $q->exists();
    }

    private function toDomain(Form2307ReceivedModel $m): Form2307Received
    {
        $cert = new Form2307Received(
            id:                   new Form2307ReceivedId((string) $m->id),
            companyId:            (string) $m->company_id,
            customerId:           $m->customer_id !== null ? (string) $m->customer_id : null,
            payorTin:             (string) $m->payor_tin,
            payorRegisteredName:  (string) $m->payor_registered_name,
            payorBranchCode:      (string) $m->payor_branch_code,
            payorAddress:         $m->payor_address !== null ? (string) $m->payor_address : null,
            certificateNo:        $m->certificate_no !== null ? (string) $m->certificate_no : null,
            atcCode:              (string) $m->atc_code,
            periodFrom:           new DateTimeImmutable($m->period_from->toIso8601String()),
            periodTo:             new DateTimeImmutable($m->period_to->toIso8601String()),
            incomePayment:        (string) $m->income_payment,
            taxWithheld:          (string) $m->tax_withheld,
            sourcePdfPath:        $m->source_pdf_path !== null ? (string) $m->source_pdf_path : null,
            entryMethod:          (string) $m->entry_method,
            journalEntryId:       $m->journal_entry_id !== null ? (string) $m->journal_entry_id : null,
            recordedBy:           (string) $m->recorded_by,
            status:               (string) $m->status,
        );

        $cert->claimedInBirFormId = $m->claimed_in_bir_form_id !== null
            ? (string) $m->claimed_in_bir_form_id
            : null;
        $cert->rejectionReason = $m->rejection_reason !== null
            ? (string) $m->rejection_reason
            : null;

        return $cert;
    }
}
