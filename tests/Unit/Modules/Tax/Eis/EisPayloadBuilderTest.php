<?php

declare(strict_types=1);

use App\Modules\Accounting\Domain\ValueObjects\Money;
use App\Modules\Sales\Domain\Entities\Customer;
use App\Modules\Sales\Domain\Entities\SalesInvoice;
use App\Modules\Sales\Domain\Entities\SalesInvoiceLine;
use App\Modules\Sales\Domain\ValueObjects\CustomerId;
use App\Modules\Sales\Domain\ValueObjects\SalesInvoiceId;
use App\Modules\Tax\Domain\Services\Eis\EisPayloadBuilder;
use App\Modules\Tax\Domain\Services\Eis\JsonCanonicalizer;

/**
 * The payload shape is signed — once we publish what we transmit to BIR,
 * any unintentional shape change invalidates the signature and breaks
 * verification on BIR's side. These tests pin the shape.
 */
beforeEach(function () {
    $this->builder      = new EisPayloadBuilder(schemaVersion: '1.0');
    $this->canonicalizer = new JsonCanonicalizer();

    $this->seller = [
        'tin'              => '000-123-456-000',
        'branch_code'      => '000',
        'registered_name'  => 'ABC TRADING INC',
        'trade_name'       => 'ABC',
        'address'          => '123 RIZAL ST, MAKATI',
        'vat_status'       => 'vat',
        'accreditation_no' => 'CAS-PTU-2026-001',
    ];

    $this->buildInvoice = function (
        string $docKind = 'cash',
        bool $isPosted = true,
        bool $isVoided = false,
    ): SalesInvoice {
        $invoice = new SalesInvoice(
            id:                SalesInvoiceId::generate(),
            companyId:         '018f0000-0000-7000-8000-000000000001',
            customerId:        new CustomerId('018f0000-0000-7000-8000-000000000020'),
            documentSeriesId:  '018f0000-0000-7000-8000-000000000030',
            docNo:             'SI-2026-000123',
            docKind:           $docKind,
            invoiceDate:       new DateTimeImmutable('2026-05-15'),
            subtotal:          Money::php('10000'),
            vatableSales:      Money::php('10000'),
            vatAmount:         Money::php('1200'),
            total:             Money::php('11200'),
        );

        $invoice->addLine(new SalesInvoiceLine(
            lineNo:      1,
            description: 'Office supplies',
            quantity:    '10',
            unitPrice:   Money::php('1000'),
            vatAmount:   Money::php('1200'),
            lineTotal:   Money::php('11200'),
            taxCodeId:   '018f0000-0000-7000-8000-000000000040',
        ));

        if ($isPosted) {
            $invoice->post('user-1', '018f0000-0000-7000-8000-000000000050');
        }
        if ($isVoided) {
            $invoice->void('Customer cancelled', 'user-2');
        }

        return $invoice;
    };

    $this->buyer = new Customer(
        id:               new CustomerId('018f0000-0000-7000-8000-000000000020'),
        companyId:        '018f0000-0000-7000-8000-000000000001',
        customerNo:       'C-001',
        registeredName:   'ACME OFFICE SUPPLIES CORP',
        tin:              '111-222-333-000',
        isVatRegistered:  true,
        isGovernment:     false,
        isSeniorCitizen:  false,
        isPwd:            false,
    );
});

it('builds a payload with seller, buyer, lines, and totals blocks', function () {
    $invoice = ($this->buildInvoice)();
    $payload = $this->builder->build($invoice, $this->buyer, $this->seller);

    expect($payload['schema_version'])->toBe('1.0')
        ->and($payload['document_number'])->toBe('SI-2026-000123')
        ->and($payload['document_type'])->toBe('sales_invoice_cash')
        ->and($payload['invoice_date'])->toBe('2026-05-15')
        ->and($payload['seller']['tin'])->toBe('000123456000')          // dashes stripped
        ->and($payload['buyer']['tin'])->toBe('111222333000')
        ->and($payload['buyer']['classification'])->toBe('vat_registered')
        ->and($payload['lines'])->toHaveCount(1)
        ->and($payload['totals']['grand_total'])->toBe('11200.0000');
});

it('classifies the buyer as government (triggers 5% withheld VAT reporting on BIR side)', function () {
    $invoice = ($this->buildInvoice)();
    $govBuyer = new Customer(
        id:               $this->buyer->id,
        companyId:        $this->buyer->companyId,
        customerNo:       'G-001',
        registeredName:   'BUREAU OF INTERNAL REVENUE',
        tin:              '999-999-999-000',
        isVatRegistered:  true,
        isGovernment:     true,
        isSeniorCitizen:  false,
        isPwd:            false,
    );

    $payload = $this->builder->build($invoice, $govBuyer, $this->seller);
    expect($payload['buyer']['classification'])->toBe('government');
});

it('classifies the buyer as senior_citizen (RA 9994) before vat_registered', function () {
    $invoice = ($this->buildInvoice)();
    $senior = new Customer(
        id:               $this->buyer->id,
        companyId:        $this->buyer->companyId,
        customerNo:       'S-001',
        registeredName:   'LOLA NENA',
        tin:              null,
        isVatRegistered:  false,
        isGovernment:     false,
        isSeniorCitizen:  true,
        isPwd:            false,
    );

    $payload = $this->builder->build($invoice, $senior, $this->seller);
    expect($payload['buyer']['classification'])->toBe('senior_citizen')
        ->and($payload['buyer']['tin'])->toBeNull();
});

it('emits document_type=sales_invoice_charge for non-cash invoices', function () {
    $invoice = ($this->buildInvoice)(docKind: 'charge');
    $payload = $this->builder->build($invoice, $this->buyer, $this->seller);

    expect($payload['document_type'])->toBe('sales_invoice_charge');
});

it('refuses to build a payload for an unposted invoice', function () {
    $invoice = ($this->buildInvoice)(isPosted: false);

    expect(fn () => $this->builder->build($invoice, $this->buyer, $this->seller))
        ->toThrow(InvalidArgumentException::class, 'not posted');
});

it('refuses to build a payload for a voided invoice (cancellation uses a different endpoint)', function () {
    $invoice = ($this->buildInvoice)(isPosted: true, isVoided: true);

    expect(fn () => $this->builder->build($invoice, $this->buyer, $this->seller))
        ->toThrow(InvalidArgumentException::class, 'voided');
});

it('rejects seller profiles missing required fields', function () {
    $invoice = ($this->buildInvoice)();
    $bad = $this->seller;
    unset($bad['tin']);

    expect(fn () => $this->builder->build($invoice, $this->buyer, $bad))
        ->toThrow(InvalidArgumentException::class, 'missing required field: tin');
});

it('rejects invalid seller vat_status values', function () {
    $invoice = ($this->buildInvoice)();
    $bad = $this->seller;
    $bad['vat_status'] = 'maybe';

    expect(fn () => $this->builder->build($invoice, $this->buyer, $bad))
        ->toThrow(InvalidArgumentException::class, 'vat_status');
});

it('produces deterministic canonical bytes — same invoice → same signature input', function () {
    $invoice = ($this->buildInvoice)();

    $bytes1 = $this->canonicalizer->canonicalize(
        $this->builder->build($invoice, $this->buyer, $this->seller),
    );
    $bytes2 = $this->canonicalizer->canonicalize(
        $this->builder->build($invoice, $this->buyer, $this->seller),
    );

    expect($bytes1)->toBe($bytes2);
});
