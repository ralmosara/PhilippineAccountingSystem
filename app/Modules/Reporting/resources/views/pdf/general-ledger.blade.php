<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>General Ledger — {{ $payload['period'] }}</title>
    <style>
        @page { size: legal landscape; margin: 0.4in; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8pt; color: #111; }
        h1 { font-size: 12pt; text-align: center; margin: 0 0 2px; }
        .subtitle, .period { text-align: center; color: #555; margin: 0 0 8px; }
        h2 { font-size: 10pt; background: #f0f0f0; padding: 6px 8px; margin: 14px 0 0; border-left: 3px solid #333; page-break-after: avoid; }
        table { width: 100%; border-collapse: collapse; }
        th { border-bottom: 1px solid #999; padding: 3px 6px; background: #fafafa; font-size: 8pt; text-align: left; }
        td { padding: 2px 6px; border-bottom: 1px dotted #eee; }
        .amount { text-align: right; font-variant-numeric: tabular-nums; }
        .opening td, .closing td { background: #f7f7f7; font-weight: 600; border-top: 1px solid #999; }
    </style>
</head>
<body>
    <h1>{{ $company->registered_name ?? '—' }}</h1>
    <div class="subtitle">TIN: {{ $company->tin ?? '—' }}</div>
    <div class="period"><strong>GENERAL LEDGER</strong> · For the period {{ $payload['period'] }}</div>

    @foreach($payload['ledgers'] as $ledger)
        <h2>{{ $ledger['account_code'] }} — {{ $ledger['account_name'] }} ({{ $ledger['normal_balance'] }} normal)</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 80px">Date</th>
                    <th style="width: 110px">Doc No.</th>
                    <th>Memo</th>
                    <th style="width: 95px" class="amount">Debit</th>
                    <th style="width: 95px" class="amount">Credit</th>
                    <th style="width: 110px" class="amount">Running Balance</th>
                </tr>
            </thead>
            <tbody>
                <tr class="opening">
                    <td colspan="5">Opening balance</td>
                    <td class="amount">{{ number_format((float) $ledger['opening_balance'], 2) }}</td>
                </tr>
                @foreach($ledger['transactions'] as $t)
                    <tr>
                        <td>{{ $t['entry_date'] }}</td>
                        <td>{{ $t['doc_no'] }}</td>
                        <td>{{ $t['memo'] ?? '' }}</td>
                        <td class="amount">{{ bccomp($t['debit'], '0', 2) > 0 ? number_format((float) $t['debit'], 2) : '' }}</td>
                        <td class="amount">{{ bccomp($t['credit'], '0', 2) > 0 ? number_format((float) $t['credit'], 2) : '' }}</td>
                        <td class="amount">{{ number_format((float) $t['running_balance'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="closing">
                    <td colspan="5">Closing balance ({{ count($ledger['transactions']) }} transactions)</td>
                    <td class="amount">{{ number_format((float) $ledger['closing_balance'], 2) }}</td>
                </tr>
            </tbody>
        </table>
    @endforeach

    <div style="margin-top: 12px; font-size: 8pt; color: #666;">
        Generated {{ now()->format('Y-m-d H:i T') }} · BIR CAS-compliant per RR 9-2009.
    </div>
</body>
</html>
