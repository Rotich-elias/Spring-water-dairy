<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoiceNumber }}</title>
    <style>
        @page { margin: 32px 36px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; }
        header { border-bottom: 2px solid #0f766e; padding-bottom: 14px; margin-bottom: 22px; }
        .brand { display: block; font-size: 21px; font-weight: bold; color: #0f766e; }
        .parent { display: block; margin-top: 2px; font-size: 11px; color: #64748b; }
        h1 { margin: 0; font-size: 24px; color: #0f766e; }
        .muted { color: #64748b; }
        .right { text-align: right; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #f1f5f9; text-align: left; padding: 8px; border: 1px solid #cbd5e1; font-size: 10px; text-transform: uppercase; letter-spacing: 0.03em; color: #475569; }
        td { padding: 8px; border: 1px solid #e2e8f0; vertical-align: top; }
        .totals td { border: none; padding: 5px 8px; }
        .totals .label { text-align: right; color: #64748b; }
        .totals .value { width: 110px; text-align: right; font-weight: bold; }
        .totals .grand { background: #f0fdfa; }
        .panel { margin-top: 18px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; }
        .panel h2 { margin: 0 0 6px; font-size: 12px; text-transform: uppercase; color: #475569; }
        .panel p { margin: 3px 0; }
        footer { position: fixed; bottom: -14px; left: 0; right: 0; text-align: center; font-size: 9px; color: #94a3b8; }
    </style>
</head>
<body>
    <header>
        <table style="border: none; margin: 0;">
            <tr>
                <td style="border: none; padding: 0;">
                    <span class="brand">{{ $platformName }}</span>
                    <span class="parent">{{ $parentCompany }}</span>
                    <div class="muted" style="margin-top: 8px;">
                        {{ $businessName }}
                        @if ($businessAddress) &middot; {{ $businessAddress }} @endif
                        @if ($businessPhone) &middot; {{ $businessPhone }} @endif
                        @if ($businessEmail) &middot; {{ $businessEmail }} @endif
                    </div>
                </td>
                <td class="right" style="border: none; padding: 0;">
                    <h1>INVOICE</h1>
                    <div class="muted">{{ $invoiceNumber }}</div>
                    <div class="muted">Issued {{ $issuedAt }}</div>
                    <div class="muted">Status: {{ $status }}</div>
                </td>
            </tr>
        </table>
    </header>

    <table>
        <tr>
            <th style="width: 50%;">Billed to</th>
            <th class="right">Request details</th>
        </tr>
        <tr>
            <td>
                <strong>{{ $farmName }}</strong>
                @if ($farmAddress)<br>{{ $farmAddress }}@endif
                @if ($farmPhone)<br>{{ $farmPhone }}@endif
                @if ($farmEmail)<br>{{ $farmEmail }}@endif
            </td>
            <td class="right">
                Requested by: {{ $requestedBy }}<br>
                Current plan: {{ $currentPlanName }}
                @if ($paymentReference)<br>Payment reference: {{ $paymentReference }}@endif
                @if ($paymentDate)<br>Payment date: {{ $paymentDate }}@endif
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Billing period</th>
                <th class="right">Amount ({{ $currency }})</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>{{ $planName }}</strong>
                    @if ($note)<br><span class="muted">{{ $note }}</span>@endif
                </td>
                <td>Monthly subscription</td>
                <td class="right">{{ number_format($amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Subtotal</td>
            <td class="value">{{ $currency }} {{ number_format($amount, 2) }}</td>
        </tr>
        <tr class="grand">
            <td class="label">Total due</td>
            <td class="value">{{ $currency }} {{ number_format($amount, 2) }}</td>
        </tr>
    </table>

    @if ($instructions)
        <div class="panel">
            <h2>Payment instructions</h2>
            <p>{!! nl2br(e($instructions)) !!}</p>
        </div>
    @endif

    <footer>
        {{ $platformName }} &middot; {{ $poweredByLabel }} &middot; {{ $invoiceNumber }}
    </footer>
</body>
</html>
