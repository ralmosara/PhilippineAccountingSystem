<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditWriterContract;
use App\Modules\Procurement\Application\Contracts\VendorBillRepositoryContract;
use App\Modules\Procurement\Application\Exceptions\VendorBillNotFoundException;
use App\Modules\Procurement\Domain\Events\Form2307Issued;
use App\Modules\Procurement\Domain\ValueObjects\VendorBillId;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DomainException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * Issues a Form 2307 (Certificate of Creditable Tax Withheld at Source)
 * for a posted vendor bill.
 *
 * Idempotent: returns the existing 2307 if one already exists for the bill.
 *
 * The PDF is rendered lazily on first download; this action only persists
 * the record and audit/event trail.
 */
final readonly class IssueForm2307
{
    public function __construct(
        private VendorBillRepositoryContract $bills,
        private AuditWriterContract $audit,
        private Dispatcher $events,
    ) {
    }

    public function execute(string $vendorBillId, string $actorId): string
    {
        $bill = $this->bills->findById(new VendorBillId($vendorBillId))
            ?? throw new VendorBillNotFoundException($vendorBillId);

        if (! $bill->isPosted()) {
            throw new DomainException('Cannot issue 2307 for an unposted vendor bill.');
        }
        if ($bill->withholdingAtcCode === null || $bill->withholdingAmount->isZero()) {
            throw new DomainException('Vendor bill has no withholding tax to certify.');
        }

        return DB::transaction(function () use ($bill, $actorId) {
            // Idempotency — return existing if present
            $existing = DB::table('tax.form_2307')
                ->where('vendor_bill_id', $bill->id->value)
                ->value('id');
            if ($existing) {
                return (string) $existing;
            }

            $form2307Id = Uuid::uuid4()->toString();
            $period = CarbonImmutable::instance($bill->billDate);
            $periodFrom = $period->startOfMonth();
            $periodTo   = $period->endOfMonth();

            DB::table('tax.form_2307')->insert([
                'id'              => $form2307Id,
                'company_id'      => $bill->companyId,
                'vendor_bill_id'  => $bill->id->value,
                'vendor_id'       => $bill->vendorId->value,
                'atc_code'        => $bill->withholdingAtcCode,
                'income_payment'  => $bill->subtotal->toPhp(),
                'tax_withheld'    => $bill->withholdingAmount->toPhp(),
                'period_from'     => $periodFrom->toDateString(),
                'period_to'       => $periodTo->toDateString(),
                'issued_at'       => now(),
                'issued_by'       => $actorId,
                'sent_to_vendor'  => false,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            $this->audit->writeEvent(
                actorId:     $actorId,
                companyId:   $bill->companyId,
                eventType:   'form2307.issued',
                aggregate:   'Form2307',
                aggregateId: $form2307Id,
                payload: [
                    'vendor_id'        => $bill->vendorId->value,
                    'vendor_bill_id'   => $bill->id->value,
                    'atc_code'         => $bill->withholdingAtcCode,
                    'income_payment'   => $bill->subtotal->toPhp(),
                    'tax_withheld'     => $bill->withholdingAmount->toPhp(),
                ],
            );

            $this->events->dispatch(new Form2307Issued(
                form2307Id:   $form2307Id,
                companyId:    $bill->companyId,
                vendorId:     $bill->vendorId->value,
                vendorBillId: $bill->id->value,
                atcCode:      $bill->withholdingAtcCode,
                taxWithheld:  $bill->withholdingAmount->toPhp(),
                periodFrom:   new DateTimeImmutable($periodFrom->toDateString()),
                periodTo:     new DateTimeImmutable($periodTo->toDateString()),
                issuedBy:     $actorId,
            ));

            return $form2307Id;
        });
    }
}
