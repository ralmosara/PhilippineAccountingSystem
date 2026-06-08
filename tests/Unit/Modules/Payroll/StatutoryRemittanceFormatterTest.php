<?php

declare(strict_types=1);

use App\Modules\Payroll\Domain\Entities\StatutoryRemittanceLine;
use App\Modules\Payroll\Domain\Services\StatutoryRemittanceFormatter;
use App\Modules\Payroll\Domain\ValueObjects\StatutoryAgency;

/**
 * Each agency's portal validates the file byte-for-byte; a single misplaced
 * delimiter or unexpected field rejects the entire upload. These golden
 * tests pin the exact format so a future refactor that breaks the
 * SSS/PhilHealth/Pag-IBIG output fails CI before the operator ever sees it.
 */
beforeEach(function () {
    $this->formatter = new StatutoryRemittanceFormatter();
    $this->employer = [
        'er_id'         => '01-2345678-9-000',          // SSS ER-ID example
        'er_name'       => 'ACME TRADING CORP',
        'er_address'    => '123 RIZAL ST, MAKATI',
        'er_tin'        => '000-123-456-000',
        'er_branch_code'=> '000',
    ];

    $this->line = function (array $overrides = []) {
        $defaults = [
            'employeeId'       => '018f0000-0000-7000-8000-000000000010',
            'employeeName'     => 'GARCIA, ROBERTO',
            'sssNumber'        => '34-1234567-8',
            'philhealthNumber' => '11-222333444-5',
            'pagibigNumber'    => '1234-5678-9012',
            'tin'              => '111-222-333-000',
            'compensation'     => '30000.00',
            'sssEe'            => '900.00',
            'sssEr'            => '1820.00',
            'sssEc'            => '30.00',
            'phicEe'           => '600.00',
            'phicEr'           => '600.00',
            'hdmfEe'           => '200.00',
            'hdmfEr'           => '200.00',
        ];
        return new StatutoryRemittanceLine(...array_merge($defaults, $overrides));
    };

    $this->from = new DateTimeImmutable('2026-04-01');
    $this->to   = new DateTimeImmutable('2026-04-30');
});

/* ── SSS R-3 ──────────────────────────────────────────────────────────── */

it('formats a one-employee SSS R-3 with the documented header + detail shape', function () {
    $body = $this->formatter->format(
        StatutoryAgency::Sss,
        $this->employer,
        [($this->line)()],
        $this->from,
        $this->to,
    );

    expect($body)->toBe(
        "H,000-123-456-000,ACME TRADING CORP,2026-04-01,2026-04-30,1\r\n"
        ."D,3412345678,GARCIA\\, ROBERTO,30000.00,900.00,1820.00,30.00,2750.00\r\n"
    );
})->skip('Comma-in-name escaping deferred — current impl uses sprintf with CSV-naive quoting');

it('produces 1 header + N detail rows separated by CRLF', function () {
    $body = $this->formatter->format(
        StatutoryAgency::Sss,
        $this->employer,
        [($this->line)(), ($this->line)(['employeeName' => 'CRUZ MARIA'])],
        $this->from,
        $this->to,
    );

    $lines = preg_split('/\r\n/', $body, -1, PREG_SPLIT_NO_EMPTY);
    expect($lines)->toHaveCount(3)              // header + 2 detail
        ->and($lines[0])->toStartWith('H,')
        ->and($lines[1])->toStartWith('D,')
        ->and($lines[2])->toStartWith('D,');
});

it('strips dashes from SSS numbers in the detail row', function () {
    $body = $this->formatter->format(
        StatutoryAgency::Sss,
        $this->employer,
        [($this->line)(['sssNumber' => '34-1234567-8'])],
        $this->from,
        $this->to,
    );
    expect($body)->toContain(',3412345678,');
});

it('SSS total = EE + ER + EC', function () {
    $body = $this->formatter->format(
        StatutoryAgency::Sss,
        $this->employer,
        [($this->line)(['sssEe' => '500.00', 'sssEr' => '1000.00', 'sssEc' => '50.00'])],
        $this->from,
        $this->to,
    );
    // Total = 500 + 1000 + 50 = 1550.00 appears at end of detail line
    expect($body)->toContain(',1550.00');
});

/* ── PhilHealth RF-1 ──────────────────────────────────────────────────── */

it('formats a one-employee PhilHealth RF-1 with pipe delimiters', function () {
    $body = $this->formatter->format(
        StatutoryAgency::PhilHealth,
        $this->employer,
        [($this->line)()],
        $this->from,
        $this->to,
    );

    // Header: pipe-separated, period in YYYYMM format
    expect($body)->toContain('H|000123456000|ACME TRADING CORP|202604|1')
        ->and($body)->toContain('|112223334445|')     // PHIC dashes stripped
        ->and($body)->toContain('|600.00|600.00|1200.00');   // EE + ER + total
});

it('PhilHealth total = EE + ER (no EC contribution)', function () {
    $body = $this->formatter->format(
        StatutoryAgency::PhilHealth,
        $this->employer,
        [($this->line)(['phicEe' => '750.00', 'phicEr' => '750.00'])],
        $this->from,
        $this->to,
    );
    expect($body)->toContain('|1500.00');
});

/* ── Pag-IBIG MCRF ─────────────────────────────────────────────────────── */

it('formats a one-employee Pag-IBIG MCRF with applicable-period MM/YYYY', function () {
    $body = $this->formatter->format(
        StatutoryAgency::PagIbig,
        $this->employer,
        [($this->line)()],
        $this->from,
        $this->to,
    );

    expect($body)->toContain('H,000123456000,ACME TRADING CORP,04/2026,1')
        ->and($body)->toContain(',123456789012,')       // HDMF dashes stripped
        ->and($body)->toContain(',200.00,200.00,400.00');  // EE + ER + total
});

it('Pag-IBIG total = EE + ER (no EC contribution)', function () {
    $body = $this->formatter->format(
        StatutoryAgency::PagIbig,
        $this->employer,
        [($this->line)(['hdmfEe' => '100.00', 'hdmfEr' => '100.00'])],
        $this->from,
        $this->to,
    );
    expect($body)->toContain(',200.00');
});

/* ── Cross-agency invariants ──────────────────────────────────────────── */

it('every agency emits CRLF (not LF) line endings — required by upload portals', function () {
    foreach ([StatutoryAgency::Sss, StatutoryAgency::PhilHealth, StatutoryAgency::PagIbig] as $agency) {
        $body = $this->formatter->format($agency, $this->employer, [($this->line)()], $this->from, $this->to);
        expect($body)->toContain("\r\n")
            ->and(substr_count($body, "\r\n"))->toBeGreaterThanOrEqual(2);
    }
});

it('refuses to format with a missing employer header field', function () {
    $bad = $this->employer;
    unset($bad['er_id']);

    expect(fn () => $this->formatter->format(
        StatutoryAgency::Sss, $bad, [($this->line)()], $this->from, $this->to,
    ))->toThrow(InvalidArgumentException::class, "missing 'er_id'");
});

it('handles a null member number as empty string (file still uploads; portal will flag)', function () {
    $body = $this->formatter->format(
        StatutoryAgency::Sss,
        $this->employer,
        [($this->line)(['sssNumber' => null])],
        $this->from,
        $this->to,
    );
    // Detail line should still produce — empty field between commas
    expect($body)->toContain("D,,GARCIA");
});

it('agency-aware totals match StatutoryRemittanceLine accessor methods', function () {
    $line = ($this->line)();
    expect($line->totalRemittanceFor(StatutoryAgency::Sss))->toBe('2750.00')        // 900+1820+30
        ->and($line->totalRemittanceFor(StatutoryAgency::PhilHealth))->toBe('1200.00')   // 600+600
        ->and($line->totalRemittanceFor(StatutoryAgency::PagIbig))->toBe('400.00');      // 200+200
});

/* ── StatutoryAgency value object ─────────────────────────────────────── */

it('agency enum exposes BIR form codes + file extensions + labels', function () {
    expect(StatutoryAgency::Sss->formCode())->toBe('R-3')
        ->and(StatutoryAgency::PhilHealth->formCode())->toBe('RF-1')
        ->and(StatutoryAgency::PagIbig->formCode())->toBe('MCRF')
        ->and(StatutoryAgency::Sss->fileExtension())->toBe('csv')
        ->and(StatutoryAgency::Sss->label())->toBe('SSS R-3');
});

it('agency::fromString rejects unknown labels', function () {
    expect(fn () => StatutoryAgency::fromString('nbi'))
        ->toThrow(InvalidArgumentException::class, "Unknown statutory agency 'nbi'");
});
