<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>BIR Form 1701Q — Quarterly Income Tax Return (Q{{ $form->period->quarter }} {{ $form->period->year }})</title>
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
        .badge-q { background: #dbeafe; border-color: #1e40af; color: #1e40af; }
        .note { font-size: 8pt; color: #666; font-style: italic; margin: 8px 0; }
    </style>
</head>
<body>
    <h1>BIR Form 1701Q</h1>
    <div class="subtitle">Quarterly Income Tax Return for Self-Employed Individuals,<br>Estates and Trusts</div>

    <div class="meta">
        <strong>{{ $company['name'] ?? '—' }}</strong><br>
        TIN: {{ $company['tin'] ?? '—' }} &nbsp;·&nbsp; RDO: {{ $company['rdo_code'] ?? '—' }}<br>
        <span class="badge badge-q">{{ $form->period->label() }} (cumulative YTD)</span>
        @if(($form->data['method'] ?? '') === 'flat_8pct')
            &nbsp;·&nbsp; <span class="badge">8% Flat Tax Election</span>
        @else
            &nbsp;·&nbsp; Graduated Rates (TRAIN)
        @endif
        @if(($form->data['deduction_method'] ?? 'itemized') === 'osd')
            &nbsp;·&nbsp; <span class="badge">OSD (40% of Gross Sales)</span>
        @endif
    </div>

    <div class="note">
        All amounts are CUMULATIVE for the year ({{ $form->period->from->format('M j') }} – {{ $form->period->to->format('M j, Y') }}).
        Tax already paid in prior quarters is subtracted at the bottom.
    </div>

    <h2>Gross Sales / Receipts and Cost (YTD)</h2>
    <table>
        @foreach(collect($lines)->whereIn('lineCode', ['22','23','24','25','26']) as $l)
            <tr>
                <td class="line-code">{{ $l->lineCode }}</td>
                <td>{{ $l->description }}</td>
                <td class="amount">{{ number_format((float) $l->amount, 2) }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Allowable Deductions (YTD)</h2>
    <table>
        @foreach(collect($lines)->whereIn('lineCode', ['27','28A','28B']) as $l)
            <tr>
                <td class="line-code">{{ $l->lineCode }}</td>
                <td>{{ $l->description }}</td>
                <td class="amount">{{ number_format((float) $l->amount, 2) }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Net Income and Taxable Income (YTD)</h2>
    <table>
        @foreach(collect($lines)->whereIn('lineCode', ['29','30','31']) as $l)
            <tr>
                <td class="line-code">{{ $l->lineCode }}</td>
                <td>{{ $l->description }}</td>
                <td class="amount">{{ number_format((float) $l->amount, 2) }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Cumulative Tax Due</h2>
    <table>
        @php $line32 = collect($lines)->firstWhere('lineCode', '32'); @endphp
        <tr>
            <td class="line-code">{{ $line32->lineCode ?? '32' }}</td>
            <td>{{ $line32->description ?? 'Computation Method' }}</td>
            <td class="amount">{{ $line32->amount ?? 'Graduated' }}</td>
        </tr>
        @php $line33 = collect($lines)->firstWhere('lineCode', '33'); @endphp
        <tr class="subtotal">
            <td class="line-code">{{ $line33->lineCode ?? '33' }}</td>
            <td>{{ $line33->description ?? 'Cumulative Tax Due' }}</td>
            <td class="amount">{{ number_format((float) ($line33->amount ?? 0), 2) }}</td>
        </tr>
    </table>

    <h2>Tax Credits and Payments</h2>
    <table>
        @foreach(collect($lines)->whereIn('lineCode', ['34A','34B']) as $l)
            <tr>
                <td class="line-code">{{ $l->lineCode }}</td>
                <td>{{ $l->description }}</td>
                <td class="amount">({{ number_format((float) $l->amount, 2) }})</td>
            </tr>
        @endforeach
        @php $line34C = collect($lines)->firstWhere('lineCode', '34C'); @endphp
        <tr class="subtotal">
            <td class="line-code">{{ $line34C->lineCode ?? '34C' }}</td>
            <td>{{ $line34C->description ?? 'Total Credits' }}</td>
            <td class="amount">({{ number_format((float) ($line34C->amount ?? 0), 2) }})</td>
        </tr>
    </table>

    <table>
        @php $line35 = collect($lines)->firstWhere('lineCode', '35'); @endphp
        <tr class="grand">
            <td class="line-code">{{ $line35->lineCode ?? '35' }}</td>
            <td>{{ $line35->description ?? 'Tax Still Due This Quarter' }}</td>
            <td class="amount">{{ number_format((float) ($line35->amount ?? 0), 2) }}</td>
        </tr>
    </table>

    <div style="margin-top: 24px; font-size: 8pt; color: #888;">
        Generated {{ now()->format('Y-m-d H:i T') }} · TRAIN Law (RA 10963) graduated brackets, OSD per RR 2-2010.
        Filing deadline: Q1 May 15, Q2 Aug 15, Q3 Nov 15. Q4 is rolled into the annual 1701 (Apr 15 next year).
    </div>
</body>
</html>
