<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 32px 36px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; }
        header { border-bottom: 2px solid #0f766e; padding-bottom: 12px; margin-bottom: 18px; }
        header h1 { margin: 0 0 4px; font-size: 20px; color: #0f766e; }
        header .subtitle { font-size: 13px; font-weight: bold; color: #334155; }
        header .meta { margin-top: 6px; color: #64748b; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #f1f5f9; text-align: left; padding: 7px 8px; border: 1px solid #cbd5e1; font-size: 10px; text-transform: uppercase; letter-spacing: 0.03em; color: #475569; }
        td { padding: 6px 8px; border: 1px solid #e2e8f0; vertical-align: top; }
        tr:nth-child(even) td { background: #f8fafc; }
        .summary th { width: 45%; }
        .empty { padding: 18px; text-align: center; color: #64748b; border: 1px dashed #cbd5e1; margin-top: 10px; }
        footer { position: fixed; bottom: -14px; left: 0; right: 0; text-align: center; font-size: 9px; color: #94a3b8; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; background: #fef3c7; color: #92400e; font-size: 9px; }
    </style>
</head>
<body>
    <header>
        <h1>{{ $branding['farm_name'] ?? 'Farm Report' }}</h1>
        <div class="subtitle">{{ $title }}</div>
        <div class="meta">
            Generated {{ $generatedAt }}
            @if (! empty($branding['farm_address'])) &middot; {{ $branding['farm_address'] }} @endif
            @if (! empty($branding['farm_contact_phone'])) &middot; {{ $branding['farm_contact_phone'] }} @endif
            @if (! empty($branding['farm_contact_email'])) &middot; {{ $branding['farm_contact_email'] }} @endif
        </div>
    </header>

    @if ($isSummary)
        <table class="summary">
            <thead>
                <tr><th>Metric</th><th>Value</th></tr>
            </thead>
            <tbody>
                @foreach ($rows as $label => $value)
                    <tr>
                        <td>{{ \Illuminate\Support\Str::headline($label) }}</td>
                        <td>{{ is_scalar($value) ? $value : json_encode($value) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @elseif (count($rows) === 0)
        <div class="empty">No records found for the selected period.</div>
    @else
        <table>
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th>{{ \Illuminate\Support\Str::headline($column) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($columns as $column)
                            <td>
                                @php($value = $row[$column] ?? null)
                                @if (is_bool($value))
                                    {{ $value ? 'Yes' : 'No' }}
                                @elseif ($column === 'in_withholding' && $value)
                                    <span class="badge">In withholding</span>
                                @else
                                    {{ $value ?? '—' }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <footer>
        {{ $branding['farm_name'] ?? 'Spring Water Dairy' }} &middot; {{ $title }} &middot; Page {PAGE_NUM} of {PAGE_COUNT}
    </footer>
</body>
</html>
