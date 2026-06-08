<?php

declare(strict_types=1);

use App\Modules\Tax\Domain\Services\Form2307CsvParser;

beforeEach(function () {
    $this->parser = new Form2307CsvParser();
});

it('parses a minimal one-row CSV with the exact header', function () {
    $csv = "payor_tin,payor_registered_name,payor_branch_code,payor_address,certificate_no,atc_code,period_from,period_to,income_payment,tax_withheld\n"
        ."999-888-777-000,GOV INC,000,Manila,C-2026-Q1,WI010,2026-01-01,2026-03-31,100000.00,5000.00\n";

    $rows = $this->parser->parse($csv);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->payorTin)->toBe('999-888-777-000')
        ->and($rows[0]->payorRegisteredName)->toBe('GOV INC')
        ->and($rows[0]->atcCode)->toBe('WI010')
        ->and($rows[0]->incomePayment)->toBe('100000.00')
        ->and($rows[0]->taxWithheld)->toBe('5000.00')
        ->and($rows[0]->rowNo)->toBe(1);
});

it('handles UTF-8 BOM at start (Excel exports)', function () {
    $csv = "\xEF\xBB\xBFpayor_tin,payor_registered_name,payor_branch_code,payor_address,certificate_no,atc_code,period_from,period_to,income_payment,tax_withheld\n"
        ."999-888-777-000,ABC,000,,,WI010,2026-01-01,2026-03-31,100,5\n";

    $rows = $this->parser->parse($csv);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->payorTin)->toBe('999-888-777-000');
});

it('handles CRLF line endings (Windows Excel)', function () {
    $csv = "payor_tin,payor_registered_name,payor_branch_code,payor_address,certificate_no,atc_code,period_from,period_to,income_payment,tax_withheld\r\n"
        ."111,A,000,,,WI010,2026-01-01,2026-03-31,1,1\r\n"
        ."222,B,000,,,WI010,2026-01-01,2026-03-31,2,2\r\n";

    expect($this->parser->parse($csv))->toHaveCount(2);
});

it('normalizes blank cells to null (caller decides if required)', function () {
    $csv = "payor_tin,payor_registered_name,payor_branch_code,payor_address,certificate_no,atc_code,period_from,period_to,income_payment,tax_withheld\n"
        .",,,,,,,,,\n";

    $rows = $this->parser->parse($csv);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->payorTin)->toBeNull()
        ->and($rows[0]->payorAddress)->toBeNull()
        ->and($rows[0]->certificateNo)->toBeNull();
});

it('defaults blank branch_code to "000" (BIR head-office convention)', function () {
    $csv = "payor_tin,payor_registered_name,payor_branch_code,payor_address,certificate_no,atc_code,period_from,period_to,income_payment,tax_withheld\n"
        ."999,A,,,,WI010,2026-01-01,2026-03-31,100,5\n";

    $rows = $this->parser->parse($csv);
    expect($rows[0]->payorBranchCode)->toBe('000');
});

it('preserves quoted fields containing commas', function () {
    $csv = "payor_tin,payor_registered_name,payor_branch_code,payor_address,certificate_no,atc_code,period_from,period_to,income_payment,tax_withheld\n"
        .'999,"GARCIA, ROBERTO",000,"123 Rizal St, Makati",C-1,WI010,2026-01-01,2026-03-31,100,5'."\n";

    $rows = $this->parser->parse($csv);
    expect($rows[0]->payorRegisteredName)->toBe('GARCIA, ROBERTO')
        ->and($rows[0]->payorAddress)->toBe('123 Rizal St, Makati');
});

it('skips blank rows silently (operator paste-artefacts)', function () {
    $csv = "payor_tin,payor_registered_name,payor_branch_code,payor_address,certificate_no,atc_code,period_from,period_to,income_payment,tax_withheld\n"
        ."999,A,000,,,WI010,2026-01-01,2026-03-31,100,5\n"
        ."\n"
        ."888,B,000,,,WI010,2026-04-01,2026-06-30,200,10\n";

    expect($this->parser->parse($csv))->toHaveCount(2);
});

it('rejects an empty file', function () {
    expect(fn () => $this->parser->parse(''))
        ->toThrow(InvalidArgumentException::class, 'CSV is empty');
});

it('rejects a header that does not match the expected columns', function () {
    $csv = "tin,name,extra_column\n111,A,foo\n";
    expect(fn () => $this->parser->parse($csv))
        ->toThrow(InvalidArgumentException::class, 'header mismatch');
});

it('treats header column matching as case-insensitive', function () {
    $csv = "PAYOR_TIN,Payor_Registered_Name,payor_branch_code,payor_address,certificate_no,ATC_CODE,period_from,period_to,income_payment,tax_withheld\n"
        ."999,A,000,,,WI010,2026-01-01,2026-03-31,100,5\n";

    expect($this->parser->parse($csv))->toHaveCount(1);
});

it('row numbers count from 1 excluding the header', function () {
    $csv = "payor_tin,payor_registered_name,payor_branch_code,payor_address,certificate_no,atc_code,period_from,period_to,income_payment,tax_withheld\n"
        ."111,A,000,,,WI010,2026-01-01,2026-03-31,100,5\n"
        ."222,B,000,,,WI010,2026-01-01,2026-03-31,200,10\n"
        ."333,C,000,,,WI010,2026-01-01,2026-03-31,300,15\n";

    $rows = $this->parser->parse($csv);
    expect($rows[0]->rowNo)->toBe(1)
        ->and($rows[1]->rowNo)->toBe(2)
        ->and($rows[2]->rowNo)->toBe(3);
});
