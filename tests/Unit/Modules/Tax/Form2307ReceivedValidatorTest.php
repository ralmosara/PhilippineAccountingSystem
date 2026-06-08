<?php

declare(strict_types=1);

use App\Modules\Tax\Domain\Entities\Form2307Received;
use App\Modules\Tax\Domain\Exceptions\InvalidPayorTinException;
use App\Modules\Tax\Domain\Services\Form2307ReceivedValidator;
use App\Modules\Tax\Domain\ValueObjects\Form2307ReceivedId;

beforeEach(function () {
    $this->validator = new Form2307ReceivedValidator();

    $this->build = function (array $overrides = []) {
        $defaults = [
            'id'                  => Form2307ReceivedId::generate(),
            'companyId'           => '018f0000-0000-7000-8000-000000000001',
            'customerId'          => null,
            'payorTin'            => '999-888-777-000',
            'payorRegisteredName' => 'GOV INC',
            'payorBranchCode'     => '000',
            'payorAddress'        => null,
            'certificateNo'       => null,
            'atcCode'             => 'WI010',
            'periodFrom'          => new DateTimeImmutable('2026-01-01'),
            'periodTo'            => new DateTimeImmutable('2026-03-31'),
            'incomePayment'       => '100000.00',
            'taxWithheld'         => '5000.00',
            'sourcePdfPath'       => null,
            'entryMethod'         => 'manual',
            'journalEntryId'      => null,
            'recordedBy'          => '018f0000-0000-7000-8000-00000000aaaa',
            'status'              => 'recorded',
        ];
        return new Form2307Received(...array_merge($defaults, $overrides));
    };
});

it('accepts a 12-digit dashed TIN (000-123-456-000)', function () {
    $cert = ($this->build)(['payorTin' => '000-123-456-000']);
    expect(fn () => $this->validator->validate($cert))->not->toThrow(Exception::class);
});

it('accepts an unformatted 12-digit TIN (000123456000)', function () {
    $cert = ($this->build)(['payorTin' => '000123456000']);
    expect(fn () => $this->validator->validate($cert))->not->toThrow(Exception::class);
});

it('accepts a 9-digit TIN (legacy single-branch taxpayers)', function () {
    $cert = ($this->build)(['payorTin' => '123-456-789']);
    expect(fn () => $this->validator->validate($cert))->not->toThrow(Exception::class);
});

it('rejects a TIN with the wrong digit count', function () {
    expect(fn () => $this->validator->validate(($this->build)(['payorTin' => '12345'])))
        ->toThrow(InvalidPayorTinException::class)
        ->and(fn () => $this->validator->validate(($this->build)(['payorTin' => '0001234567890'])))
        ->toThrow(InvalidPayorTinException::class);
});

it('rejects a withholding rate above 35% as a data-entry error', function () {
    // ₱100,000 income with ₱40,000 tax = 40% rate — implausible
    $cert = ($this->build)(['incomePayment' => '100000.00', 'taxWithheld' => '40000.00']);
    expect(fn () => $this->validator->validate($cert))
        ->toThrow(DomainException::class, 'Implausible withholding rate');
});

it('accepts the highest legitimate rate (30% — top professional fee bracket)', function () {
    $cert = ($this->build)(['incomePayment' => '100000.00', 'taxWithheld' => '30000.00']);
    expect(fn () => $this->validator->validate($cert))->not->toThrow(Exception::class);
});

it('passes a 0-income cert without dividing by zero', function () {
    $cert = ($this->build)(['incomePayment' => '0.00', 'taxWithheld' => '0.00']);
    expect(fn () => $this->validator->validate($cert))->not->toThrow(Exception::class);
});

it('rejects a period that spans two fiscal years', function () {
    $cert = ($this->build)([
        'periodFrom' => new DateTimeImmutable('2025-12-15'),
        'periodTo'   => new DateTimeImmutable('2026-01-15'),
    ]);
    expect(fn () => $this->validator->validate($cert))
        ->toThrow(DomainException::class, 'spans fiscal years');
});
