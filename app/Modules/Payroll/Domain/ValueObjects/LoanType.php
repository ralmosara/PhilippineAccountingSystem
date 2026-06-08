<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\ValueObjects;

enum LoanType: string
{
    case SssSalaryLoan  = 'sss_salary_loan';
    case HdmfMpl        = 'hdmf_mpl';
    case HdmfHousing    = 'hdmf_housing';

    public function label(): string
    {
        return match ($this) {
            self::SssSalaryLoan => 'SSS Salary Loan',
            self::HdmfMpl       => 'HDMF Multi-Purpose Loan',
            self::HdmfHousing   => 'HDMF Housing Loan',
        };
    }
}
