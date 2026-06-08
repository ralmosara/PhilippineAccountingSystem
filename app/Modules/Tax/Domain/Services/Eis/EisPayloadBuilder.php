<?php

declare(strict_types=1);

namespace App\Modules\Tax\Domain\Services\Eis;

use App\Modules\Sales\Domain\Entities\Customer;
use App\Modules\Sales\Domain\Entities\SalesInvoice;
use App\Modules\Sales\Domain\Entities\SalesInvoiceLine;
use InvalidArgumentException;

/**
 * Maps a posted SalesInvoice (+ Customer + seller profile) to the BIR EIS
 * JSON schema (RR 8-2022, RR 6-2024).
 *
 * The shape produced here is the **canonical input** to the signer; once a
 * SalesInvoice is finalized it must produce the same bytes every time, even
 * if re-built years later for an audit re-verification.
 *
 * Notes on the schema:
 *   - BIR's actual JSON schema is provisioned per-taxpayer through the EIS
 *     sandbox onboarding portal. The field names below mirror the public
 *     reference materials (RR 8-2022 Annex A) and will be aliased to the
 *     real schema in HttpEisGatewayClient if BIR's onboarding kit renames them.
 *   - All monetary fields are strings (see JsonCanonicalizer for why).
 *   - Discount lines are itemised separately so BIR can audit
 *     RA 9994 (senior) / RA 10754 (PWD) compliance.
 *
 * @phpstan-type SellerProfile array{
 *     tin: string,
 *     branch_code: string,
 *     registered_name: string,
 *     trade_name: ?string,
 *     address: string,
 *     vat_status: 'vat'|'non-vat',
 *     accreditation_no: ?string,
 * }
 */
final readonly class EisPayloadBuilder
{
    public function __construct(
        private string $schemaVersion = '1.0',          // bump on BIR schema update
    ) {
    }

    /**
     * @param  SellerProfile  $seller
     * @return array<string, mixed>
     */
    public function build(SalesInvoice $invoice, Customer $customer, array $seller): array
    {
        if (! $invoice->isPosted()) {
            throw new InvalidArgumentException(
                "Cannot transmit invoice {$invoice->docNo} to EIS — invoice is not posted.",
            );
        }
        if ($invoice->isVoided()) {
            // Voided invoices use a different EIS endpoint (cancellation submission),
            // not regular transmission.
            throw new InvalidArgumentException(
                "Cannot transmit voided invoice {$invoice->docNo} via the issuance endpoint. "
                .'Use the cancellation endpoint instead.',
            );
        }

        $this->assertSeller($seller);

        return [
            'schema_version' => $this->schemaVersion,
            'document_type'  => $invoice->docKind === 'cash'
                ? 'sales_invoice_cash'
                : 'sales_invoice_charge',
            'document_number' => $invoice->docNo,
            'issued_at'       => $invoice->postedAt?->format(DATE_ATOM),
            'invoice_date'    => $invoice->invoiceDate->format('Y-m-d'),
            'currency'        => $invoice->currency,
            'fx_rate'         => $invoice->fxRate,

            'seller' => [
                'tin'              => $this->stripTinDashes($seller['tin']),
                'branch_code'      => $seller['branch_code'],
                'registered_name'  => $seller['registered_name'],
                'trade_name'       => $seller['trade_name'],
                'address'          => $seller['address'],
                'vat_status'       => $seller['vat_status'],
                'accreditation_no' => $seller['accreditation_no'],
            ],

            'buyer' => [
                'tin'             => $customer->tin !== null
                    ? $this->stripTinDashes($customer->tin)
                    : null,
                'registered_name' => $customer->registeredName,
                'classification'  => $this->buyerClassification($customer),
            ],

            'lines' => array_map(
                fn (SalesInvoiceLine $l) => $this->buildLine($l),
                $invoice->lines,
            ),

            'totals' => [
                'vatable_sales'       => $invoice->vatableSales->amount,
                'vat_amount'          => $invoice->vatAmount->amount,
                'zero_rated_sales'    => $invoice->vatZeroRatedSales->amount,
                'exempt_sales'        => $invoice->vatExemptSales->amount,
                'discount_amount'     => $invoice->discountAmount->amount,
                'senior_pwd_discount' => $invoice->seniorPwdDiscount->amount,
                'withheld_vat'        => $invoice->withheldVat->amount,
                'subtotal'            => $invoice->subtotal->amount,
                'grand_total'         => $invoice->total->amount,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function buildLine(SalesInvoiceLine $line): array
    {
        return [
            'line_no'      => $line->lineNo,
            'description'  => $line->description,
            'quantity'     => $line->quantity,
            'unit_price'   => $line->unitPrice->amount,
            'discount_pct' => $line->discountPct,
            'discount_amount' => $line->discountAmount?->amount ?? '0.0000',
            'vat_amount'   => $line->vatAmount->amount,
            'line_total'   => $line->lineTotal->amount,
            'item_id'      => $line->itemId,
            'tax_code_id'  => $line->taxCodeId,
        ];
    }

    private function buyerClassification(Customer $customer): string
    {
        if ($customer->isGovernment) {
            return 'government';            // 5% withheld VAT applies
        }
        if ($customer->isSeniorCitizen) {
            return 'senior_citizen';        // RA 9994: 20% disc + VAT exempt
        }
        if ($customer->isPwd) {
            return 'pwd';                   // RA 10754
        }
        if ($customer->isVatRegistered) {
            return 'vat_registered';
        }
        return 'non_vat';
    }

    /** @param SellerProfile $seller */
    private function assertSeller(array $seller): void
    {
        foreach (['tin', 'branch_code', 'registered_name', 'address', 'vat_status'] as $required) {
            if (! isset($seller[$required]) || $seller[$required] === '') {
                throw new InvalidArgumentException("Seller profile missing required field: {$required}");
            }
        }
        if (! in_array($seller['vat_status'], ['vat', 'non-vat'], true)) {
            throw new InvalidArgumentException(
                "Seller vat_status must be 'vat' or 'non-vat'; got '{$seller['vat_status']}'.",
            );
        }
    }

    /** "000-123-456-000" → "000123456000" (BIR EIS expects 12 digits, no dashes). */
    private function stripTinDashes(string $tin): string
    {
        return preg_replace('/\D+/', '', $tin) ?? '';
    }
}
