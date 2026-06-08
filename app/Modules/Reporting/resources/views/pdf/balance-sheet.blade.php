<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Balance Sheet — As of {{ $payload['as_of_date'] }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #111; }
        h1 { font-size: 13pt; text-align: center; margin: 0 0 2px; }
        .subtitle { text-align: center; color: #555; margin: 0 0 4px; }
        .period { text-align: center; font-size: 10pt; margin-bottom: 16px; }
        h2 { font-size: 10pt; background: #f0f0f0; padding: 4px 6px; margin: 12px 0 4px; border-left: 3px solid #333; }
        h3 { font-size: 9pt; margin: 8px 0 4px; color: #444; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 6px; }
        .amount { text-align: right; font-variant-numeric: tabular-nums; }
        .indent { padding-left: 16px; color: #444; }
        .subtotal td { border-top: 1px solid #999; font-weight: 700; padding-top: 4px; }
        .total td { border-top: 2px solid #333; border-bottom: 3px double #333; font-weight: 700; padding: 6px; font-size: 10pt; }
        .balanced { color: #058527; }
        .unbalanced { color: #c0152b; font-weight: 700; }
    </style>
</head>
<body>
    <h1>{{ $company->registered_name ?? '—' }}</h1>
    <div class="subtitle">TIN: {{ $company->tin ?? '—' }}</div>
    <div class="period"><strong>Balance Sheet</strong><br>As of {{ $payload['as_of_date'] }}</div>

    @php
        $sections = $payload['sections'];
        $renderSection = function ($title, $lines) {
            if (empty($lines)) return;
            echo "<h3>{$title}</h3><table>";
            $total = '0';
            foreach ($lines as $l) {
                $total = bcadd($total, $l['amount'], 2);
                echo '<tr><td class="indent">'.htmlspecialchars($l['account_code'].' — '.$l['account_name']).'</td>';
                echo '<td class="amount" style="width:120px">'.number_format((float) $l['amount'], 2).'</td></tr>';
            }
            echo '<tr class="subtotal"><td>Total</td><td class="amount">'.number_format((float) $total, 2).'</td></tr>';
            echo '</table>';
        };
    @endphp

    <h2>ASSETS</h2>
    @php
        $renderSection('Current Assets',     $sections['current_asset']);
        $renderSection('Non-Current Assets', $sections['noncurrent_asset']);
    @endphp
    <table><tr class="total"><td>TOTAL ASSETS</td><td class="amount" style="width:120px">{{ number_format((float) $payload['total_assets'], 2) }}</td></tr></table>

    <h2>LIABILITIES</h2>
    @php
        $renderSection('Current Liabilities',     $sections['current_liability']);
        $renderSection('Non-Current Liabilities', $sections['noncurrent_liability']);
    @endphp

    <h2>EQUITY</h2>
    @php $renderSection('Equity Accounts', $sections['equity']); @endphp
    <table><tr><td class="indent">Current Year Earnings</td>
              <td class="amount" style="width:120px">{{ number_format((float) $payload['current_year_earnings'], 2) }}</td></tr></table>

    <table>
        <tr class="total"><td>TOTAL LIABILITIES + EQUITY</td>
            <td class="amount" style="width:120px">{{ number_format((float) $payload['liabilities_and_equity'], 2) }}</td></tr>
    </table>

    <div style="margin-top: 16px; font-size: 9pt;">
        @if($payload['is_balanced'])
            <span class="balanced">✓ Balance Sheet is balanced: Total Assets = Total Liabilities + Equity</span>
        @else
            <span class="unbalanced">⚠ OUT OF BALANCE — investigate</span>
        @endif
    </div>
</body>
</html>
