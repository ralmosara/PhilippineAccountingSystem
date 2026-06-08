<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Purchases Book — {{ $payload['period'] }}</title>
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
    <div class="period"><strong>PURCHASES BOOK</strong> · For the period {{ $payload['period'] }}</div>

    <table>
        <thead>
            <tr>
                <th style="width: 75px">Date</th>
                <th style="width: 120px">Vendor Inv. No.</th>
                <th>Vendor</th>
                <th style="width: 120px">TIN</th>
                <th class="amount" style="width: 100px">Subtotal</th>
                <th class="amount" style="width: 100px">Input VAT</th>
                <th class="amount" style="width: 70px">WT ATC</th>
                <th class="amount" style="width: 90px">WT Amount</th>
                <th class="amount" style="width: 105px">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payload['rows'] as $r)
                <tr>
                    <td>{{ $r['bill_date'] }}</td>
                    <td>{{ $r['vendor_invoice_no'] }}</td>
                    <td>{{ $r['vendor_name'] }}</td>
                    <td>{{ $r['vendor_tin'] ?? '—' }}</td>
                    <td class="amount">{{ number_format((float) $r['subtotal'], 2) }}</td>
                    <td class="amount">{{ number_format((float) $r['vat_input'], 2) }}</td>
                    <td>{{ $r['withholding_atc_code'] ?? '—' }}</td>
                    <td class="amount">{{ number_format((float) $r['withholding_amount'], 2) }}</td>
                    <td class="amount">{{ number_format((float) $r['total'], 2) }}</td>
                </tr>
            @endforeach
            <tr class="totals">
                <td colspan="4">TOTALS ({{ $payload['row_count'] }} bills)</td>
                <td class="amount">{{ number_format((float) $payload['totals']['subtotal'],    2) }}</td>
                <td class="amount">{{ number_format((float) $payload['totals']['vat_input'],   2) }}</td>
                <td>—</td>
                <td class="amount">{{ number_format((float) $payload['totals']['withholding'], 2) }}</td>
                <td class="amount">{{ number_format((float) $payload['totals']['total'],       2) }}</td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top: 12px; font-size: 8pt; color: #666;">
        Generated {{ now()->format('Y-m-d H:i T') }} · BIR CAS-compliant per RR 9-2009.
    </div>
</body>
</html>
