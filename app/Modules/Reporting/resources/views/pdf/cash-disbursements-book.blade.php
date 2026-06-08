<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cash Disbursements Book — {{ $payload['period'] }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5pt; color: #111; }
        h1 { font-size: 12pt; text-align: center; margin: 0 0 2px; }
        .subtitle, .period { text-align: center; color: #555; margin: 0 0 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { border-bottom: 1px solid #333; padding: 4px 6px; background: #f0f0f0; text-align: left; }
        td { padding: 3px 6px; border-bottom: 1px dotted #eee; }
        .amount { text-align: right; font-variant-numeric: tabular-nums; }
        .totals td { background: #f0f0f0; font-weight: 700; border-top: 2px solid #333; border-bottom: 3px double #333; }
        .by-method { margin-top: 14px; font-size: 9pt; }
        .by-method table { width: auto; }
        .by-method th, .by-method td { padding: 4px 12px; }
    </style>
</head>
<body>
    <h1>{{ $company->registered_name ?? '—' }}</h1>
    <div class="subtitle">TIN: {{ $company->tin ?? '—' }}</div>
    <div class="period"><strong>CASH DISBURSEMENTS BOOK</strong> · For the period {{ $payload['period'] }}</div>

    <table>
        <thead>
            <tr>
                <th style="width: 90px">Date</th>
                <th style="width: 120px">CV No.</th>
                <th>Payee (Vendor)</th>
                <th style="width: 110px">Method</th>
                <th style="width: 140px">Reference No.</th>
                <th class="amount" style="width: 115px">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payload['rows'] as $r)
                <tr>
                    <td>{{ $r['payment_date'] }}</td>
                    <td>{{ $r['cv_no'] }}</td>
                    <td>{{ $r['payee_name'] }}</td>
                    <td>{{ $r['payment_method'] }}</td>
                    <td>{{ $r['reference_no'] ?? '—' }}</td>
                    <td class="amount">{{ number_format((float) $r['amount'], 2) }}</td>
                </tr>
            @endforeach
            <tr class="totals">
                <td colspan="5">TOTAL ({{ $payload['row_count'] }} disbursements)</td>
                <td class="amount">{{ number_format((float) $payload['total'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="by-method">
        <strong>Breakdown by payment method:</strong>
        <table>
            @foreach($payload['by_method'] ?? [] as $method => $amount)
                <tr><td>{{ $method }}</td><td class="amount">{{ number_format((float) $amount, 2) }}</td></tr>
            @endforeach
        </table>
    </div>

    <div style="margin-top: 16px; font-size: 8pt; color: #666;">
        Generated {{ now()->format('Y-m-d H:i T') }} · BIR CAS-compliant per RR 9-2009.
    </div>
</body>
</html>
