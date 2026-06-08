<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>BIR Form 1701 — Annual Income Tax Return ({{ $form->period->year }})</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #111; }
        h1 { font-size: 13pt; text-align: center; margin: 0 0 2px; }
        .subtitle { font-size: 10pt; text-align: center; color: #444; margin: 0 0 8px; }
        .meta { text-align: center; color: #555; margin: 0 0 16px; }
        h2 { font-size: 10pt; background: #f0f0f0; padding: 4px 6px; margin: 12px 0 4px; border-left: 3px solid #333; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 3px 8px; }
        .line-code { width: 60px; font-family: monospace; color: #666; }
        .amount { text-align: right; font-variant-numeric: tabular-nums; width: 150px; }
        .subtotal td { border-top: 1px solid #999; font-weight: 700; padding-top: 4px; background: #fafafa; }
        .grand td { border-top: 2px solid #333; border-bottom: 3px double #333; font-weight: 700; padding: 6px; font-size: 11pt; background: #fafafa; }
        .badge { display: inline-block; padding: 2px 8px; background: #fef3c7; border: 1px solid #92400e; border-radius: 3px; font-size: 9pt; color: #92400e; }
    </style>
</head>
<body>
    <h1>BIR Form 1701</h1>
    <div class="subtitle">Annual Income Tax Return for Self-Employed Individuals,<br>Estates and Trusts</div>

    <div class="meta">
        <strong>{{ $company['name'] ?? '—' }}</strong><br>
        TIN: {{ $company['tin'] ?? '—' }} &nbsp;·&nbsp; RDO: {{ $company['rdo_code'] ?? '—' }}<br>
        For taxable year {{ $form->period->year }}
        @if(($form->data['method'] ?? '') === 'flat_8pct')
            &nbsp;·&nbsp; <span class="badge">8% Flat Tax Election</span>
        @else
            &nbsp;·&nbsp; Graduated Rates (TRAIN)
        @endif
        @if(($form->data['deduction_method'] ?? 'itemized') === 'osd')
            &nbsp;·&nbsp; <span class="badge">OSD elected (40% of gross sales, RR 2-2010)</span>
        @endif
    </div>

    <h2>Gross Sales / Receipts and Cost</h2>
    <table>
        @foreach(collect($lines)->whereIn('lineCode', ['38','39','40','41','42']) as $l)
            <tr>
                <td class="line-code">{{ $l->lineCode }}</td>
                <td>{{ $l->description }}</td>
                <td class="amount">{{ number_format((float) $l->amount, 2) }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Allowable Deductions</h2>
    <table>
        @foreach(collect($lines)->whereIn('lineCode', ['43','43A','43B']) as $l)
            <tr>
                <td class="line-code">{{ $l->lineCode }}</td>
                <td>{{ $l->description }}</td>
                <td class="amount">{{ number_format((float) $l->amount, 2) }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Net Income and Taxable Income</h2>
    <table>
        @foreach(collect($lines)->whereIn('lineCode', ['44','45','46']) as $l)
            <tr>
                <td class="line-code">{{ $l->lineCode }}</td>
                <td>{{ $l->description }}</td>
                <td class="amount">{{ number_format((float) $l->amount, 2) }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Tax Due</h2>
    <table>
        @php $line47 = collect($lines)->firstWhere('lineCode', '47'); @endphp
        <tr>
            <td class="line-code">{{ $line47->lineCode ?? '47' }}</td>
            <td>{{ $line47->description ?? 'Computation Method' }}</td>
            <td class="amount">{{ $line47->amount ?? 'Graduated' }}</td>
        </tr>
        @php $line48 = collect($lines)->firstWhere('lineCode', '48'); @endphp
        <tr class="subtotal">
            <td class="line-code">{{ $line48->lineCode ?? '48' }}</td>
            <td>{{ $line48->description ?? 'Tax Due' }}</td>
            <td class="amount">{{ number_format((float) ($line48->amount ?? 0), 2) }}</td>
        </tr>
    </table>

    <h2>Tax Credits and Payments</h2>
    <table>
        @foreach(collect($lines)->whereIn('lineCode', ['49A','49B','49C']) as $l)
            <tr>
                <td class="line-code">{{ $l->lineCode }}</td>
                <td>{{ $l->description }}</td>
                <td class="amount">({{ number_format((float) $l->amount, 2) }})</td>
            </tr>
        @endforeach
        @php $line49D = collect($lines)->firstWhere('lineCode', '49D'); @endphp
        <tr class="subtotal">
            <td class="line-code">{{ $line49D->lineCode ?? '49D' }}</td>
            <td>{{ $line49D->description ?? 'Total Credits' }}</td>
            <td class="amount">({{ number_format((float) ($line49D->amount ?? 0), 2) }})</td>
        </tr>
    </table>

    <table>
        @php $line50 = collect($lines)->firstWhere('lineCode', '50'); @endphp
        <tr class="grand">
            <td class="line-code">{{ $line50->lineCode ?? '50' }}</td>
            <td>{{ $line50->description ?? 'Tax Still Due / (Overpayment)' }}</td>
            <td class="amount">{{ number_format((float) ($line50->amount ?? 0), 2) }}</td>
        </tr>
    </table>

    <div style="margin-top: 24px; font-size: 8pt; color: #888;">
        Generated {{ now()->format('Y-m-d H:i T') }} · TRAIN Law (RA 10963) graduated brackets, with optional 8% flat election.
        Filing deadline: 15 April for calendar-year filers.
    </div>
</body>
</html>
