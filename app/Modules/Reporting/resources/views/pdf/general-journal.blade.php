<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>General Journal — {{ $payload['period'] }}</title>
    <style>
        @page { size: legal landscape; margin: 0.4in; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8pt; color: #111; }
        h1 { font-size: 12pt; text-align: center; margin: 0 0 2px; }
        .subtitle, .period { text-align: center; color: #555; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { border-bottom: 1px solid #333; padding: 4px 6px; background: #f0f0f0; font-size: 8pt; text-align: left; }
        td { padding: 2px 6px; vertical-align: top; }
        .entry-header { background: #fafafa; border-top: 1px solid #ddd; font-weight: 600; }
        .indent { padding-left: 24px; }
        .amount { text-align: right; font-variant-numeric: tabular-nums; }
        .grand-total td { border-top: 2px solid #333; border-bottom: 3px double #333; font-weight: 700; padding: 6px; }
    </style>
</head>
<body>
    <h1>{{ $company->registered_name ?? '—' }}</h1>
    <div class="subtitle">TIN: {{ $company->tin ?? '—' }}</div>
    <div class="period"><strong>GENERAL JOURNAL</strong> · For the period {{ $payload['period'] }}</div>

    <table>
        <thead>
            <tr>
                <th style="width: 80px">Date</th>
                <th style="width: 110px">JV No.</th>
                <th>Account / Memo</th>
                <th style="width: 100px" class="amount">Debit</th>
                <th style="width: 100px" class="amount">Credit</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payload['entries'] as $entry)
                <tr class="entry-header">
                    <td>{{ $entry['entry_date'] }}</td>
                    <td>{{ $entry['doc_no'] }}</td>
                    <td colspan="3"><em>{{ $entry['memo'] ?? '(no memo)' }} ({{ $entry['source'] }})</em></td>
                </tr>
                @foreach($entry['lines'] as $line)
                    <tr>
                        <td></td>
                        <td></td>
                        <td class="indent">{{ $line['account_code'] }} — {{ $line['account_name'] }}@if($line['memo']) · <small style="color:#666">{{ $line['memo'] }}</small>@endif</td>
                        <td class="amount">{{ bccomp($line['debit'], '0', 2) > 0 ? number_format((float) $line['debit'], 2) : '' }}</td>
                        <td class="amount">{{ bccomp($line['credit'], '0', 2) > 0 ? number_format((float) $line['credit'], 2) : '' }}</td>
                    </tr>
                @endforeach
            @endforeach
            <tr class="grand-total">
                <td colspan="3">TOTALS ({{ $payload['entry_count'] }} entries)</td>
                <td class="amount">{{ number_format((float) $payload['total_debit'], 2) }}</td>
                <td class="amount">{{ number_format((float) $payload['total_credit'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top: 12px; font-size: 8pt; color: #666;">
        @if($payload['is_balanced'] ?? false) ✓ @else ⚠ @endif
        Generated {{ now()->format('Y-m-d H:i T') }} · BIR CAS-compliant per RR 9-2009.
    </div>
</body>
</html>
