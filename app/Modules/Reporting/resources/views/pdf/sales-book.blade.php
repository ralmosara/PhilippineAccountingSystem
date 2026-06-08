<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Sales Book — {{ $payload['period'] }}</title>
    <style>
        @page { size: legal landscape; margin: 0.35in; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 7.5pt; color: #111; }
        h1 { font-size: 12pt; text-align: center; margin: 0 0 2px; }
        .subtitle, .period { text-align: center; color: #555; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { border-bottom: 1px solid #333; padding: 3px 5px; background: #f0f0f0; font-size: 7.5pt; text-align: left; }
        td { padding: 2px 5px; border-bottom: 1px dotted #eee; }
        .amount { text-align: right; font-variant-numeric: tabular-nums; }
        .totals td { background: #f0f0f0; font-weight: 700; border-top: 2px solid #333; border-bottom: 3px double #333; }
    </style>
</head>
<body>
    <h1>{{ $company->registered_name ?? '—' }}</h1>
    <div class="subtitle">TIN: {{ $company->tin ?? '—' }}</div>
    <div class="period"><strong>SALES BOOK</strong> · For the period {{ $payload['period'] }}</div>

    <table>
        <thead>
            <tr>
                <th style="width: 70px">Date</th>
                <th style="width: 100px">SI No.</th>
                <th>Customer</th>
                <th style="width: 110px">TIN</th>
                <th class="amount" style="width: 85px">Vatable</th>
                <th class="amount" style="width: 75px">Zero-Rated</th>
                <th class="amount" style="width: 75px">Exempt</th>
                <th class="amount" style="width: 75px">VAT 12%</th>
                <th class="amount" style="width: 70px">SC/PWD</th>
                <th class="amount" style="width: 70px">WT VAT</th>
                <th class="amount" style="width: 85px">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payload['rows'] as $r)
                <tr>
                    <td>{{ $r['invoice_date'] }}</td>
                    <td>{{ $r['doc_no'] }}</td>
                    <td>{{ $r['customer_name'] }}</td>
                    <td>{{ $r['customer_tin'] ?? '—' }}</td>
                    <td class="amount">{{ number_format((float) $r['vatable_sales'], 2) }}</td>
                    <td class="amount">{{ number_format((float) $r['vat_zero_rated_sales'], 2) }}</td>
                    <td class="amount">{{ number_format((float) $r['vat_exempt_sales'], 2) }}</td>
                    <td class="amount">{{ number_format((float) $r['vat_amount'], 2) }}</td>
                    <td class="amount">{{ number_format((float) $r['senior_pwd_discount'], 2) }}</td>
                    <td class="amount">{{ number_format((float) $r['withheld_vat'], 2) }}</td>
                    <td class="amount">{{ number_format((float) $r['total'], 2) }}</td>
                </tr>
            @endforeach
            <tr class="totals">
                <td colspan="4">TOTALS ({{ $payload['row_count'] }} invoices)</td>
                <td class="amount">{{ number_format((float) $payload['totals']['vatable'],      2) }}</td>
                <td class="amount">{{ number_format((float) $payload['totals']['zero'],         2) }}</td>
                <td class="amount">{{ number_format((float) $payload['totals']['exempt'],       2) }}</td>
                <td class="amount">{{ number_format((float) $payload['totals']['vat'],          2) }}</td>
                <td class="amount">{{ number_format((float) $payload['totals']['senior_pwd'],   2) }}</td>
                <td class="amount">{{ number_format((float) $payload['totals']['withheld_vat'], 2) }}</td>
                <td class="amount">{{ number_format((float) $payload['totals']['total'],        2) }}</td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top: 12px; font-size: 8pt; color: #666;">
        Generated {{ now()->format('Y-m-d H:i T') }} · BIR CAS-compliant per RR 9-2009.
        Voided invoices excluded; sequence_no preserved in the audit trail.
    </div>
</body>
</html>
