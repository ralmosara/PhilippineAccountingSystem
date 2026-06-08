<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>BIR Form 1702Q — Quarterly Income Tax Return (Q{{ $form->period->quarter }} {{ $form->period->year }})</title>
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
        .badge-q { background: #dbeafe; border-color: #1e40af; color: #1e40af; }
        .note { font-size: 8pt; color: #666; font-style: italic; margin: 8px 0; }
    </style>
</head>
<body>
    <h1>BIR Form 1702Q</h1>
    <div class="subtitle">Quarterly Income Tax Return for Corporations<br>(Subject to the Regular CREATE-Act Rate)</div>

    <div class="meta">
        <strong>{{ $company['name'] ?? '—' }}</strong><br>
        TIN: {{ $company['tin'] ?? '—' }} &nbsp;·&nbsp; RDO: {{ $company['rdo_code'] ?? '—' }}<br>
        <span class="badge badge-q">{{ $form->period->label() }} (cumulative YTD)</span>
        @if(($form->data['is_msme'] ?? false))
            &nbsp;·&nbsp; <span class="badge">MSME (20% rate under CREATE Act)</span>
        @else
            &nbsp;·&nbsp; Regular rate (25%)
        @endif
        @if(($form->data['deduction_method'] ?? 'itemized') === 'osd')
            &nbsp;·&nbsp; <span class="badge">OSD (40% of Gross Income)</span>
        @endif
    </div>

    <div class="note">
        All amounts are CUMULATIVE for the year ({{ $form->period->from->format('M j') }} – {{ $form->period->to->format('M j, Y') }}).
        Tax already paid in prior quarters is subtracted at the bottom.
    </div>

    <h2>Gross Sales / Receipts and Cost (YTD)</h2>
    <table>
        @foreach(collect($lines)->whereIn('lineCode', ['15','16','16A','17','18']) as $l)
            <tr>
                <td class="line-code">{{ $l->lineCode }}</td>
                <td>{{ $l->description }}</td>
                <td class="amount">{{ number_format((float) $l->amount, 2) }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Allowable Deductions (YTD)</h2>
    <table>
        @foreach(collect($lines)->whereIn('lineCode', ['19','19A','19B']) as $l)
            <tr>
                <td class="line-code">{{ $l->lineCode }}</td>
                <td>{{ $l->description }}</td>
                <td class="amount">{{ number_format((float) $l->amount, 2) }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Tax Computation</h2>
    <table>
        @foreach(collect($lines)->whereIn('lineCode', ['20','21','21A','21B']) as $l)
            <tr>
                <td class="line-code">{{ $l->lineCode }}</td>
                <td>{{ $l->description }}</td>
                <td class="amount">{{ is_numeric($l->amount) ? number_format((float) $l->amount, 2) : $l->amount }}</td>
            </tr>
        @endforeach
        @php $line21C = collect($lines)->firstWhere('lineCode', '21C'); @endphp
        <tr class="subtotal">
            <td class="line-code">{{ $line21C->lineCode ?? '21C' }}</td>
            <td>{{ $line21C->description ?? 'Cumulative Tax Due' }}</td>
            <td class="amount">{{ number_format((float) ($line21C->amount ?? 0), 2) }}</td>
        </tr>
    </table>

    <h2>Tax Credits and Payments</h2>
    <table>
        @foreach(collect($lines)->whereIn('lineCode', ['22A','22B']) as $l)
            <tr>
                <td class="line-code">{{ $l->lineCode }}</td>
                <td>{{ $l->description }}</td>
                <td class="amount">({{ number_format((float) $l->amount, 2) }})</td>
            </tr>
        @endforeach
        @php $line22C = collect($lines)->firstWhere('lineCode', '22C'); @endphp
        <tr class="subtotal">
            <td class="line-code">{{ $line22C->lineCode ?? '22C' }}</td>
            <td>{{ $line22C->description ?? 'Total Credits' }}</td>
            <td class="amount">({{ number_format((float) ($line22C->amount ?? 0), 2) }})</td>
        </tr>
    </table>

    <table>
        @php $line23 = collect($lines)->firstWhere('lineCode', '23'); @endphp
        <tr class="grand">
            <td class="line-code">{{ $line23->lineCode ?? '23' }}</td>
            <td>{{ $line23->description ?? 'Tax Still Due This Quarter' }}</td>
            <td class="amount">{{ number_format((float) ($line23->amount ?? 0), 2) }}</td>
        </tr>
    </table>

    <div style="margin-top: 24px; font-size: 8pt; color: #888;">
        Generated {{ now()->format('Y-m-d H:i T') }} · CREATE Act (RA 11534) post-MSME rate determination, OSD per RR 2-2010.
        Filing deadline: 60 days after each quarter-end (Q1 May 30, Q2 Aug 29, Q3 Nov 29).
        Q4 is rolled into the annual 1702-RT (Apr 15 next year).
    </div>
</body>
</html>
