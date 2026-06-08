<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>BIR Form 1702-RT — Annual Income Tax Return ({{ $form->period->year }})</title>
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
        .badge { display: inline-block; padding: 2px 8px; background: #e8f5e8; border: 1px solid #058527; border-radius: 3px; font-size: 9pt; color: #058527; }
    </style>
</head>
<body>
    <h1>BIR Form 1702-RT</h1>
    <div class="subtitle">Annual Income Tax Return for Corporations<br>(Subject to the Regular Income Tax Rate)</div>

    <div class="meta">
        <strong>{{ $company['name'] ?? '—' }}</strong><br>
        TIN: {{ $company['tin'] ?? '—' }} &nbsp;·&nbsp; RDO: {{ $company['rdo_code'] ?? '—' }}<br>
        For taxable year {{ $form->period->year }}
        @if(($form->data['is_msme'] ?? false))
            &nbsp;·&nbsp; <span class="badge">MSME (20% rate under CREATE Act)</span>
        @else
            &nbsp;·&nbsp; Regular rate (25%)
        @endif
        @if(($form->data['deduction_method'] ?? 'itemized') === 'osd')
            &nbsp;·&nbsp; <span class="badge">OSD elected (40% of gross income, RR 2-2010)</span>
        @endif
    </div>

    <h2>Gross Sales / Receipts and Cost of Sales</h2>
    <table>
        @foreach(collect($lines)->whereIn('lineCode', ['14','15','15A','16','17']) as $l)
            <tr>
                <td class="line-code">{{ $l->lineCode }}</td>
                <td>{{ $l->description }}</td>
                <td class="amount">{{ number_format((float) $l->amount, 2) }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Allowable Deductions</h2>
    <table>
        @foreach(collect($lines)->whereIn('lineCode', ['18','18A','18B']) as $l)
            <tr>
                <td class="line-code">{{ $l->lineCode }}</td>
                <td>{{ $l->description }}</td>
                <td class="amount">{{ number_format((float) $l->amount, 2) }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Tax Computation</h2>
    <table>
        @foreach(collect($lines)->whereIn('lineCode', ['19','20','20A','20B']) as $l)
            <tr>
                <td class="line-code">{{ $l->lineCode }}</td>
                <td>{{ $l->description }}</td>
                <td class="amount">{{ is_numeric($l->amount) ? number_format((float) $l->amount, 2) : $l->amount }}</td>
            </tr>
        @endforeach
        <tr class="subtotal">
            @php $line20C = collect($lines)->firstWhere('lineCode', '20C'); @endphp
            <td class="line-code">{{ $line20C->lineCode ?? '20C' }}</td>
            <td>{{ $line20C->description ?? 'Tax Due' }}</td>
            <td class="amount">{{ number_format((float) ($line20C->amount ?? 0), 2) }}</td>
        </tr>
    </table>

    <h2>Tax Credits and Payments</h2>
    <table>
        @foreach(collect($lines)->whereIn('lineCode', ['21A','22','22A']) as $l)
            <tr>
                <td class="line-code">{{ $l->lineCode }}</td>
                <td>{{ $l->description }}</td>
                <td class="amount">({{ number_format((float) $l->amount, 2) }})</td>
            </tr>
        @endforeach
        <tr class="subtotal">
            @php $line22B = collect($lines)->firstWhere('lineCode', '22B'); @endphp
            <td class="line-code">{{ $line22B->lineCode ?? '22B' }}</td>
            <td>{{ $line22B->description ?? 'Total Tax Credits' }}</td>
            <td class="amount">({{ number_format((float) ($line22B->amount ?? 0), 2) }})</td>
        </tr>
    </table>

    <table>
        @php $line23 = collect($lines)->firstWhere('lineCode', '23'); @endphp
        <tr class="grand">
            <td class="line-code">{{ $line23->lineCode ?? '23' }}</td>
            <td>{{ $line23->description ?? 'Tax Still Due / (Overpayment)' }}</td>
            <td class="amount">{{ number_format((float) ($line23->amount ?? 0), 2) }}</td>
        </tr>
    </table>

    <div style="margin-top: 24px; font-size: 8pt; color: #888;">
        Generated {{ now()->format('Y-m-d H:i T') }} · CREATE Act (RA 11534) post-MSME rate determination.
        Filing deadline: 15 April for calendar-year filers. Submit via eBIRForms with this PDF as reference.
    </div>
</body>
</html>
