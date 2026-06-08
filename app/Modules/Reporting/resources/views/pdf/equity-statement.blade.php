<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Statement of Changes in Equity — {{ $payload['period'] }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #111; }
        h1 { font-size: 13pt; text-align: center; margin: 0 0 2px; }
        .subtitle, .period { text-align: center; color: #555; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th { padding: 6px 8px; background: #f0f0f0; border-bottom: 1px solid #333; text-align: left; font-size: 9pt; }
        td { padding: 4px 8px; border-bottom: 1px dotted #eee; }
        .amount { text-align: right; font-variant-numeric: tabular-nums; }
        .total td { background: #f0f0f0; font-weight: 700; border-top: 2px solid #333; }
        .grand td { background: #fafafa; font-weight: 700; border-top: 2px solid #333; border-bottom: 3px double #333; padding: 8px; font-size: 11pt; }
    </style>
</head>
<body>
    <h1>{{ $company->registered_name ?? '—' }}</h1>
    <div class="subtitle">TIN: {{ $company->tin ?? '—' }}</div>
    <div class="period"><strong>Statement of Changes in Equity</strong> · For the period {{ $payload['period'] }}</div>

    <table>
        <thead>
            <tr>
                <th style="width: 90px">Code</th>
                <th>Equity Component</th>
                <th class="amount" style="width: 120px">Beginning</th>
                <th class="amount" style="width: 120px">Movement</th>
                <th class="amount" style="width: 120px">Ending</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payload['accounts'] as $row)
                <tr>
                    <td>{{ $row['account_code'] }}</td>
                    <td>{{ $row['account_name'] }}</td>
                    <td class="amount">{{ number_format((float) $row['beginning_balance'], 2) }}</td>
                    <td class="amount">{{ number_format((float) $row['movement'], 2) }}</td>
                    <td class="amount">{{ number_format((float) $row['ending_balance'], 2) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="2">Subtotal — Equity Components</td>
                <td class="amount">{{ number_format((float) $payload['total_beginning'], 2) }}</td>
                <td class="amount">{{ number_format((float) $payload['total_movement'], 2) }}</td>
                <td class="amount">{{ number_format((float) $payload['total_ending'], 2) }}</td>
            </tr>
            <tr>
                <td></td>
                <td>Net Income for the Period</td>
                <td class="amount">—</td>
                <td class="amount">{{ number_format((float) $payload['net_income_for_period'], 2) }}</td>
                <td class="amount">{{ number_format((float) $payload['net_income_for_period'], 2) }}</td>
            </tr>
            <tr class="grand">
                <td colspan="2">TOTAL EQUITY</td>
                <td class="amount">—</td>
                <td class="amount">—</td>
                <td class="amount">{{ number_format((float) $payload['total_equity'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top: 16px; font-size: 8pt; color: #888;">
        Generated {{ now()->format('Y-m-d H:i T') }} · PFRS for SMEs Section 6 / Full PFRS IAS 1.
        Net Income flows into Current Year Earnings at period-end and reclassifies to Retained Earnings at year-end close.
    </div>
</body>
</html>
