<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $payment->invoice_no }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #222; }
        .head { display: flex; justify-content: space-between; border-bottom: 2px solid #333; padding-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #ddd; }
        .total { font-size: 16px; font-weight: bold; }
        .muted { color: #777; }
    </style>
</head>
<body>
    <div class="head">
        <div>
            <h2>{{ config('app.name') }}</h2>
            <div class="muted">Invoice {{ $payment->invoice_no }}</div>
        </div>
        <div style="text-align:right">
            <div>{{ $payment->created_at->format('Y-m-d H:i') }}</div>
            <div class="muted">{{ $payment->gateway }}</div>
        </div>
    </div>

    <p><strong>{{ __('messages.billed_to') }}:</strong> {{ $payment->user?->name ?? '-' }}
        ({{ $payment->user?->email ?? '-' }})</p>

    <table>
        <thead>
            <tr><th>{{ ui('plan') }}</th><th>{{ __('messages.period') }}</th><th>{{ ui('amount') }}</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $payment->plan_slug }}</td>
                <td>{{ $payment->user?->licenses()->latest()->first()?->type ?? '-' }}</td>
                <td>Rp {{ number_format($payment->amount, 0, ',', '.') }}</td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" class="total">{{ __('messages.total') }}</td>
                <td class="total">Rp {{ number_format($payment->amount, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    <p class="muted" style="margin-top:40px">{{ __('messages.invoice_footer') }}</p>
</body>
</html>
