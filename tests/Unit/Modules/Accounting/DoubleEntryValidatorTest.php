<?php

declare(strict_types=1);

use App\Modules\Accounting\Domain\Entities\JournalEntry;
use App\Modules\Accounting\Domain\Entities\JournalLine;
use App\Modules\Accounting\Domain\Exceptions\UnbalancedJournalException;
use App\Modules\Accounting\Domain\Services\DoubleEntryValidator;
use App\Modules\Accounting\Domain\ValueObjects\AccountId;
use App\Modules\Accounting\Domain\ValueObjects\JournalEntryId;
use App\Modules\Accounting\Domain\ValueObjects\Money;

beforeEach(function () {
    $this->validator = new DoubleEntryValidator();
    $this->entry = new JournalEntry(
        id:               JournalEntryId::generate(),
        companyId:        '018f0000-0000-7000-8000-000000000001',
        fiscalPeriodId:   '018f0000-0000-7000-8000-000000000002',
        documentSeriesId: '018f0000-0000-7000-8000-000000000003',
        docNo:            'JV-2026-000001',
        entryDate:        new DateTimeImmutable('2026-05-15'),
        source:           'manual',
    );
});

it('accepts a balanced 2-line journal entry', function () {
    $debit  = new AccountId('018f0000-0000-7000-8000-000000000010');
    $credit = new AccountId('018f0000-0000-7000-8000-000000000011');

    $this->entry->addLine(new JournalLine(1, $debit,  Money::php('1000'), Money::zero(),     Money::php('1000')));
    $this->entry->addLine(new JournalLine(2, $credit, Money::zero(),     Money::php('1000'), Money::php('-1000')));

    expect(fn () => $this->validator->validate($this->entry))->not->toThrow(Exception::class);
});

it('accepts a balanced multi-line journal entry (e.g., sales with VAT)', function () {
    $ar    = new AccountId('018f0000-0000-7000-8000-000000000010');
    $sales = new AccountId('018f0000-0000-7000-8000-000000000011');
    $vat   = new AccountId('018f0000-0000-7000-8000-000000000012');

    // DR AR ₱11,200 / CR Sales ₱10,000 / CR Output VAT ₱1,200
    $this->entry->addLine(new JournalLine(1, $ar,    Money::php('11200'), Money::zero(),       Money::php('11200')));
    $this->entry->addLine(new JournalLine(2, $sales, Money::zero(),       Money::php('10000'), Money::php('-10000')));
    $this->entry->addLine(new JournalLine(3, $vat,   Money::zero(),       Money::php('1200'),  Money::php('-1200')));

    expect(fn () => $this->validator->validate($this->entry))->not->toThrow(Exception::class);
});

it('throws UnbalancedJournalException when debits do not equal credits', function () {
    $debit  = new AccountId('018f0000-0000-7000-8000-000000000010');
    $credit = new AccountId('018f0000-0000-7000-8000-000000000011');

    $this->entry->addLine(new JournalLine(1, $debit,  Money::php('1000'), Money::zero(),     Money::php('1000')));
    $this->entry->addLine(new JournalLine(2, $credit, Money::zero(),     Money::php('999'),  Money::php('-999')));

    expect(fn () => $this->validator->validate($this->entry))
        ->toThrow(UnbalancedJournalException::class, 'is unbalanced');
});

it('exception carries the per-side totals for diagnostic display', function () {
    $debit  = new AccountId('018f0000-0000-7000-8000-000000000010');
    $credit = new AccountId('018f0000-0000-7000-8000-000000000011');

    $this->entry->addLine(new JournalLine(1, $debit,  Money::php('500'), Money::zero(),     Money::php('500')));
    $this->entry->addLine(new JournalLine(2, $credit, Money::zero(),     Money::php('300'), Money::php('-300')));

    try {
        $this->validator->validate($this->entry);
        $this->fail('Expected UnbalancedJournalException');
    } catch (UnbalancedJournalException $e) {
        expect($e->debitsTotal)->toBe('500.00')
            ->and($e->creditsTotal)->toBe('300.00');
    }
});
