<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>BIR Form 1604-E — Annual Information Return on Expanded WT ({{ $form->period->year }})</title>
    <style>
        @page { size: legal landscape; margin: 0.4in; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8pt; color: #111; }
        h1 { font-size: 13pt; text-align: center; margin: 0 0 2px; }
        .subtitle { font-size: 9pt; text-align: center; color: #444; margin: 0 0 8px; }
        .meta { text-align: center; color: #555; margin: 0 0 12px; }
        h2 { font-size: 10pt; background: #f0f0f0; padding: 4px 6px; margin: 12px 0 4px; border-left: 3px solid #333; page-break-after: avoid; }
        table { width: 100%; border-collapse: collapse; }
        th { border-bottom: 1px solid #333; padding: 3px 5px; background: #f0f0f0; text-align: left; font-size: 8pt; }
        td { padding: 2px 5px; border-bottom: 1px dotted #eee; }
        .amount { text-align: right; font-variant-numeric: tabular-nums; }
        .totals td { background: #fafafa; font-weight: 700; border-top: 2px solid #333; }
        .summary { width: 60%; margin: 8px auto; }
        .summary td:first-child { width: 60%; font-weight: 600; }
    </style>
</head>
<body>
    <h1>BIR Form 1604-E</h1>
    <div class="subtitle">Annual Information Return of Creditable Income Taxes Withheld (Expanded)</div>
    <div class="meta">
        <strong>{{ $company['name'] ?? '—' }}</strong> · TIN: {{ $company['tin'] ?? '—' }} · For taxable year {{ $form->period->year }}
    </div>

    <h2>Summary</h2>
    <table class="summary">
        @foreach(collect($lines)->whereIn('lineCode', ['A', 'B', 'C', 'D']) as $l)
            <tr>
                <td>{{ $l->description }}</td>
                <td class="amount">{{ is_numeric($l->amount) ? number_format((float) $l->amount, 2) : $l->amount }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Per-ATC Breakdown</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 100px">ATC</th>
                <th>Description / Payees</th>
                <th class="amount" style="width: 140px">Total Withheld</th>
            </tr>
        </thead>
        <tbody>
            @foreach(collect($lines)->filter(fn($l) => str_starts_with($l->lineCode, 'B') && $l->lineCode !== 'B') as $l)
                <tr>
                    <td>{{ $l->breakdown['atc'] ?? $l->lineCode }}</td>
                    <td>{{ $l->description }}</td>
                    <td class="amount">{{ number_format((float) $l->amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Schedule 1 — Alphalist of Payees Subject to EWT ({{ count($form->alphalistEntries) }} entries)</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 130px">TIN</th>
                <th>Payee (Vendor)</th>
                <th style="width: 75px">ATC</th>
                <th class="amount" style="width: 130px">Income Payment</th>
                <th class="amount" style="width: 130px">Tax Withheld</th>
            </tr>
        </thead>
        <tbody>
            @php $tIncome = '0'; $tWt = '0'; @endphp
            @foreach($form->alphalistEntries as $e)
                @php
                    $tIncome = bcadd($tIncome, $e->incomePayment, 2);
                    $tWt     = bcadd($tWt,     $e->taxWithheld,   2);
                @endphp
                <tr>
                    <td>{{ $e->tin }}</td>
                    <td>{{ $e->registeredName }}</td>
                    <td>{{ $e->atcCode }}</td>
                    <td class="amount">{{ number_format((float) $e->incomePayment, 2) }}</td>
                    <td class="amount">{{ number_format((float) $e->taxWithheld, 2) }}</td>
                </tr>
            @endforeach
            <tr class="totals">
                <td colspan="3">Total</td>
                <td class="amount">{{ number_format((float) $tIncome, 2) }}</td>
                <td class="amount">{{ number_format((float) $tWt, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top: 14px; font-size: 8pt; color: #888;">
        Generated {{ now()->format('Y-m-d H:i T') }} · Filing deadline: March 1 of the following year (RR 11-2018).
        Upload the accompanying .dat file in eBIRForms as the alphalist attachment.
    </div>
</body>
</html>
