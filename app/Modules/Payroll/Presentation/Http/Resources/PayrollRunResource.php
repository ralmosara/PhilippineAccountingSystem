<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Payroll\Infrastructure\Persistence\Eloquent\PayrollRunModel
 */
final class PayrollRunResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'run_no'            => $this->run_no,
            'run_type'          => $this->run_type,
            'payroll_period_id' => $this->payroll_period_id,
            'status'            => $this->status,
            'computed_at'       => $this->computed_at?->toIso8601String(),
            'approved_at'       => $this->approved_at?->toIso8601String(),
            'approved_by'       => $this->approved_by,
            'paid_at'           => $this->paid_at?->toIso8601String(),
            'journal_entry_id'  => $this->journal_entry_id,
            'payslip_count'     => $this->whenLoaded('payslips', fn () => $this->payslips->count()),
            'totals'            => $this->whenLoaded('payslips', fn () => [
                'gross'              => (string) $this->payslips->sum('gross_compensation'),
                'sss_ee'             => (string) $this->payslips->sum('sss_ee'),
                'phic_ee'            => (string) $this->payslips->sum('phic_ee'),
                'hdmf_ee'            => (string) $this->payslips->sum('hdmf_ee'),
                'withholding_tax'    => (string) $this->payslips->sum('withholding_tax'),
                'net_pay'            => (string) $this->payslips->sum('net_pay'),
            ]),
            'payslips' => $this->whenLoaded('payslips', fn () => $this->payslips->map(fn ($p) => [
                'id'                  => $p->id,
                'employee_id'         => $p->employee_id,
                'gross_compensation'  => (string) $p->gross_compensation,
                'sss_ee'              => (string) $p->sss_ee,
                'phic_ee'             => (string) $p->phic_ee,
                'hdmf_ee'             => (string) $p->hdmf_ee,
                'withholding_tax'     => (string) $p->withholding_tax,
                'net_pay'             => (string) $p->net_pay,
                'lines'               => $p->relationLoaded('lines') ? $p->lines->map(fn ($l) => [
                    'line_no'     => (int) $l->line_no,
                    'line_type'   => $l->line_type,
                    'code'        => $l->code,
                    'description' => $l->description,
                    'amount'      => (string) $l->amount,
                ]) : null,
            ])),
        ];
    }
}
