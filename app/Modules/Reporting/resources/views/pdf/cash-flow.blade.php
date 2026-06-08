<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cash Flow Statement — {{ $payload['period'] }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #111; }
        h1 { font-size: 13pt; text-align: center; margin: 0 0 2px; }
        .subtitle, .period { text-align: center; color: #555; margin: 0 0 4px; }
        h2 { font-size: 10pt; background: #f0f0f0; padding: 4px 6px; margin: 12px 0 4px; border-left: 3px solid #333; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 6px; }
        .amount { text-align: right; font-variant-numeric: tabular-nums; width: 130px; }
        .indent { padding-left: 16px; color: #444; }
        .subtotal td { border-top: 1px solid #999; font-weight: 700; padding-top: 4px; background: #fafafa; }
        .grand td { border-top: 2px solid #333; border-bottom: 3px double #333; font-weight: 700; padding: 6px; font-size: 11pt; }
        .reconciled { color: #058527; }
        .unreconciled { color: #c0152b; font-weight: 700; }
    </style>
</head>
<body>
    <h1>{{ $company->registered_name ?? '—' }}</h1>
    <div class="subtitle">TIN: {{ $company->tin ?? '—' }}</div>
    <div class="period"><strong>Cash Flow Statement</strong> · {{ $payload['period'] }}</div>

    @php
        $section = function ($title, $lines, $total) {
            if (empty($lines)) return "<h2>{$title}</h2><table><tr><td class='indent'>No transactions in this category.</td><td class='amount'>0.00</td></tr></table>";
            $html = "<h2>{$title}</h2><table>";
            foreach ($lines as $l) {
                $html .= '<tr><td class="indent">'.htmlspecialchars(mb_strimwidth($l['description'], 0, 100, '…')).'</td>'
                       . '<td class="amount">'.number_format((float) $l['amount'], 2).'</td></tr>';
            }
            $html .= '<tr class="subtotal"><td>Net cash from this activity</td><td class="amount">'.number_format((float) $total, 2).'</td></tr></table>';
            return $html;
        };
    @endphp

    {!! $section('Cash Flows from Operating Activities', $payload['operating'], $payload['total_operating']) !!}
    {!! $section('Cash Flows from Investing Activities', $payload['investing'], $payload['total_investing']) !!}
    {!! $section('Cash Flows from Financing Activities', $payload['financing'], $payload['total_financing']) !!}

    <table>
        <tr class="subtotal"><td>Net Increase / (Decrease) in Cash</td><td class="amount">{{ number_format((float) $payload['net_change'], 2) }}</td></tr>
        <tr><td>Cash at Beginning of Period</td><td class="amount">{{ number_format((float) $payload['beginning_cash'], 2) }}</td></tr>
        <tr class="grand"><td>Cash at End of Period</td><td class="amount">{{ number_format((float) $payload['ending_cash'], 2) }}</td></tr>
    </table>

    <div style="margin-top: 16px; font-size: 9pt;">
        @if($payload['reconciles'])
            <span class="reconciled">✓ Reconciled: beginning + net change = ending cash.</span>
        @else
            <span class="unreconciled">⚠ Does not reconcile — investigate uncategorized cash movements.</span>
        @endif
    </div>

    <div style="margin-top: 12px; font-size: 8pt; color: #888;">
        Generated {{ now()->format('Y-m-d H:i T') }} · PFRS Section 7 (direct method with type-based categorization).
        Cash movements are classified by the dominant offsetting account's PFRS classification.
    </div>
</body>
</html>
