<?php

declare(strict_types=1);

use App\Modules\Tax\Domain\Entities\AlphalistEntry;
use App\Modules\Tax\Domain\Services\DatFileFormatter;

/**
 * BIR DAT format compliance — a single field-order mismatch causes the
 * entire DAT to be rejected by eBIRForms validators, so we pin the exact
 * byte-level format here.
 */
beforeEach(function () {
    $this->formatter = new DatFileFormatter();
});

it('produces a SAWT DAT with one header row + one detail per entry, CRLF separated', function () {
    $entries = [
        new AlphalistEntry(
            schedule:        'sawt',
            tin:             '111-222-333-000',
            registeredName:  'ACME OFFICE SUPPLIES CORP',
            atcCode:         'WC010',
            incomePayment:   '10000.00',
            taxWithheld:     '100.00',
            paymentDate:     new DateTimeImmutable('2026-04-15'),
        ),
    ];

    $dat = $this->formatter->formatSawt($entries, [
        'tin'             => '000-123-456-000',
        'registered_name' => 'ABC TRADING INC',
        'period_from'     => '2026-04-01',
        'period_to'       => '2026-06-30',
    ]);

    // Header row + detail row + trailing CRLF
    expect($dat)->toBe(
        "H|000123456000|ABC TRADING INC|2026-04-01|2026-06-30|1\r\n"
        ."D|111222333000|ACME OFFICE SUPPLIES CORP|WC010|2026-04-15|10000.00|100.00\r\n"
    );
});

it('strips dashes from TINs to produce 12-digit unformatted IDs', function () {
    $dat = $this->formatter->formatSawt([], [
        'tin'             => '123-456-789-000',
        'registered_name' => 'Test Co',
        'period_from'     => '2026-01-01',
        'period_to'       => '2026-03-31',
    ]);

    // 123-456-789-000 → 123456789000 (12 digits)
    expect($dat)->toContain('H|123456789000|');
});

it('formats amounts with exactly 2 decimal places', function () {
    $entries = [
        new AlphalistEntry(
            schedule:       'sawt',
            tin:            '111-222-333-000',
            registeredName: 'Test',
            atcCode:        'WC010',
            incomePayment:  '1234.5',     // odd precision input
            taxWithheld:    '12.345',     // truncates/rounds to 2dp
            paymentDate:    new DateTimeImmutable('2026-04-15'),
        ),
    ];

    $dat = $this->formatter->formatSawt($entries, [
        'tin' => '000', 'registered_name' => 'X',
        'period_from' => '2026-01-01', 'period_to' => '2026-03-31',
    ]);

    expect($dat)->toContain('|1234.50|12.34');
});

it('truncates long registered names to 100 characters', function () {
    $long = str_repeat('A', 150);

    $dat = $this->formatter->formatSawt([], [
        'tin' => '000', 'registered_name' => $long,
        'period_from' => '2026-01-01', 'period_to' => '2026-03-31',
    ]);

    expect($dat)->toContain('|'.str_repeat('A', 100).'|');
});

it('produces an annual alphalist DAT with year header', function () {
    $entries = [
        new AlphalistEntry(
            schedule:       '7_2',
            tin:            '111-111-111-000',
            registeredName: 'GARCIA, ROBERTO',
            atcCode:        'WC050',
            incomePayment:  '600000.00',
            taxWithheld:    '50000.00',
            taxType:        'C',
        ),
    ];

    $dat = $this->formatter->formatAnnualAlphalist($entries, [
        'tin'             => '000-123-456-000',
        'registered_name' => 'ABC TRADING INC',
        'year'            => 2025,
    ]);

    expect($dat)->toBe(
        "H|000123456000|ABC TRADING INC|2025|1\r\n"
        ."D|7_2|111111111000|GARCIA, ROBERTO|WC050|C|600000.00|50000.00\r\n"
    );
});
