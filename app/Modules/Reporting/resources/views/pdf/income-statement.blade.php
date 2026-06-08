<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Income Statement — {{ $payload['period'] }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #111; }
        h1 { font-size: 13pt; text-align: center; margin: 0 0 2px; }
        .subtitle { text-align: center; color: #555; margin: 0 0 4px; }
        .period { text-align: center; font-size: 10pt; margin-bottom: 16px; }
        h2 { font-size: 10pt; background: #f0f0f0; padding: 4px 6px; margin: 12px 0 4px; border-left: 3px solid #333; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 6px; }
        .amount { text-align: right; font-variant-numeric: tabular-nums; width: 120px; }
        .indent { padding-left: 16px; color: #444; }
        .subtotal td { border-top: 1px solid #999; font-weight: 700; padding-top: 4px; }
        .grand-total td { border-top: 2px solid #333; border-bottom: 3px double #333; font-weight: 700; padding: 6px; font-size: 11pt; }
        .negative { color: #c0152b; }
    </style>
</head>
<body>
    <h1>{{ $company->registered_name ?? '—' }}</h1>
    <div class="subtitle">TIN: {{ $company->tin ?? '—' }}</div>
    <div class="period"><strong>Income Statement (Statement of Profit or Loss)</strong><br>For the period {{ $payload['period'] }}</div>

    @php
        $section = function ($title, $lines) {
            if (empty($lines)) return '';
            $html = "<h2>{$title}</h2><table>";
            foreach ($lines as $l) {
                $html .= '<tr><td class="indent">'.htmlspecialchars($l['account_code'].' — '.$l['account_name']).'</td>'
                       . '<td class="amount">'.number_format((float) $l['amount'], 2).'</td></tr>';
            }
            return $html.'</table>';
        };
        $signedAmount = fn ($v) => number_format((float) $v, 2);
    @endphp

    {!! $section('Revenue', $payload['revenue']) !!}
    <table><tr class="subtotal"><td>Total Revenue</td><td class="amount">{{ $signedAmount($payload['totals']['total_revenue']) }}</td></tr></table>

    {!! $section('Cost of Sales', $payload['cost_of_sales']) !!}
    <table><tr class="subtotal"><td>Total Cost of Sales</td><td class="amount">({{ $signedAmount($payload['totals']['total_cost_of_sales']) }})</td></tr></table>

    <table><tr class="subtotal" style="background:#fafafa;"><td><strong>GROSS PROFIT</strong></td><td class="amount"><strong>{{ $signedAmount($payload['totals']['gross_profit']) }}</strong></td></tr></table>

    {!! $section('Operating Expenses', $payload['operating_expenses']) !!}
    <table><tr class="subtotal"><td>Total Operating Expenses</td><td class="amount">({{ $signedAmount($payload['totals']['total_operating_expenses']) }})</td></tr></table>

    <table><tr class="subtotal" style="background:#fafafa;"><td><strong>OPERATING INCOME</strong></td><td class="amount"><strong>{{ $signedAmount($payload['totals']['operating_income']) }}</strong></td></tr></table>

    {!! $section('Other Income', $payload['other_income']) !!}
    {!! $section('Other Expenses', $payload['other_expenses']) !!}

    <table>
        <tr class="subtotal"><td>Income Before Tax</td><td class="amount">{{ $signedAmount($payload['totals']['income_before_tax']) }}</td></tr>
    </table>

    {!! $section('Income Tax Expense', $payload['income_tax']) !!}

    <table>
        <tr class="grand-total"><td>NET INCOME</td><td class="amount">{{ $signedAmount($payload['totals']['net_income']) }}</td></tr>
    </table>

    <div style="margin-top: 16px; font-size: 8pt; color: #888;">
        Generated {{ now()->format('Y-m-d H:i T') }} · PFRS-aligned (per PFRS for SMEs / Full PFRS Section 5: Statement of Comprehensive Income)
    </div>
</body>
</html>
