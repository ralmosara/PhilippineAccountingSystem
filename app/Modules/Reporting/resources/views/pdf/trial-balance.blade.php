<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Trial Balance — {{ $payload['period'] }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #111; }
        h1 { font-size: 13pt; text-align: center; margin: 0 0 2px; }
        .subtitle { text-align: center; color: #555; font-size: 9pt; margin: 0 0 4px; }
        .period { text-align: center; font-size: 10pt; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px 6px; }
        th { border-bottom: 2px solid #333; text-align: left; font-size: 9pt; background: #f0f0f0; }
        td { border-bottom: 1px solid #eee; }
        .amount { text-align: right; font-variant-numeric: tabular-nums; }
        .total { font-weight: 700; border-top: 2px solid #333; border-bottom: 3px double #333; }
        .balanced { color: #058527; }
        .unbalanced { color: #c0152b; }
    </style>
</head>
<body>
    <h1>{{ $company->registered_name ?? '—' }}</h1>
    <div class="subtitle">TIN: {{ $company->tin ?? '—' }}</div>
    <div class="period"><strong>Trial Balance</strong><br>For the period {{ $payload['period'] }}</div>

    <table>
        <thead>
            <tr>
                <th style="width: 90px">Code</th>
                <th>Account</th>
                <th style="width: 100px" class="amount">Debit</th>
                <th style="width: 100px" class="amount">Credit</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payload['lines'] as $line)
                <tr>
                    <td>{{ $line['account_code'] }}</td>
                    <td>{{ $line['account_name'] }}</td>
                    <td class="amount">{{ bccomp($line['debit'], '0', 2) > 0 ? number_format((float) $line['debit'], 2) : '' }}</td>
                    <td class="amount">{{ bccomp($line['credit'], '0', 2) > 0 ? number_format((float) $line['credit'], 2) : '' }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="2">TOTALS</td>
                <td class="amount">{{ number_format((float) $payload['total_debit'], 2) }}</td>
                <td class="amount">{{ number_format((float) $payload['total_credit'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top: 16px; font-size: 9pt;">
        @if($payload['is_balanced'])
            <span class="balanced">✓ Trial Balance is balanced (debits = credits)</span>
        @else
            <span class="unbalanced">⚠ Trial Balance is OUT OF BALANCE — investigate immediately</span>
        @endif
        <br>
        Generated {{ now()->format('Y-m-d H:i T') }} by Philippine Accounting System.
    </div>
</body>
</html>
