<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain\Entities;

use App\Modules\Reporting\Domain\ValueObjects\ReportPeriod;

final class IncomeStatement
{
    /** @var list<FinancialStatementLine> */
    public array $revenue = [];

    /** @var list<FinancialStatementLine> */
    public array $costOfSales = [];

    /** @var list<FinancialStatementLine> */
    public array $operatingExpenses = [];

    /** @var list<FinancialStatementLine> */
    public array $otherIncome = [];

    /** @var list<FinancialStatementLine> */
    public array $otherExpenses = [];

    /** @var list<FinancialStatementLine> */
    public array $incomeTax = [];

    public string $totalRevenue            = '0.00';
    public string $totalCostOfSales        = '0.00';
    public string $totalOperatingExpenses  = '0.00';
    public string $totalOtherIncome        = '0.00';
    public string $totalOtherExpenses      = '0.00';
    public string $totalIncomeTax          = '0.00';

    public function __construct(
        public readonly string $companyId,
        public readonly ReportPeriod $period,
    ) {
    }

    public function addRevenue(FinancialStatementLine $line): void
    {
        $this->revenue[] = $line;
        $this->totalRevenue = bcadd($this->totalRevenue, $line->amount, 2);
    }

    public function addCostOfSales(FinancialStatementLine $line): void
    {
        $this->costOfSales[] = $line;
        $this->totalCostOfSales = bcadd($this->totalCostOfSales, $line->amount, 2);
    }

    public function addOperatingExpense(FinancialStatementLine $line): void
    {
        $this->operatingExpenses[] = $line;
        $this->totalOperatingExpenses = bcadd($this->totalOperatingExpenses, $line->amount, 2);
    }

    public function addOtherIncome(FinancialStatementLine $line): void
    {
        $this->otherIncome[] = $line;
        $this->totalOtherIncome = bcadd($this->totalOtherIncome, $line->amount, 2);
    }

    public function addOtherExpense(FinancialStatementLine $line): void
    {
        $this->otherExpenses[] = $line;
        $this->totalOtherExpenses = bcadd($this->totalOtherExpenses, $line->amount, 2);
    }

    public function addIncomeTax(FinancialStatementLine $line): void
    {
        $this->incomeTax[] = $line;
        $this->totalIncomeTax = bcadd($this->totalIncomeTax, $line->amount, 2);
    }

    public function grossProfit(): string
    {
        return bcsub($this->totalRevenue, $this->totalCostOfSales, 2);
    }

    public function operatingIncome(): string
    {
        return bcsub($this->grossProfit(), $this->totalOperatingExpenses, 2);
    }

    public function incomeBeforeTax(): string
    {
        return bcadd(
            bcsub($this->operatingIncome(), $this->totalOtherExpenses, 2),
            $this->totalOtherIncome,
            2,
        );
    }

    public function netIncome(): string
    {
        return bcsub($this->incomeBeforeTax(), $this->totalIncomeTax, 2);
    }
}
