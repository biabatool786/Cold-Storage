<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>body{font-family:Georgia,serif;margin:2rem}table{width:100%;border-collapse:collapse;margin-top:1rem}th,td{border:1px solid #ccc;padding:.45rem;text-align:left}</style>
</head>
<body>
    <h1>{{ strtoupper($invoice->kind) }} invoice {{ $invoice->number }}</h1>
    <p>Customer: {{ $invoice->customer?->name }}</p>
    <p>Date: {{ $invoice->invoice_date }} · Due: {{ $invoice->due_date }} · Status: {{ $invoice->status }}</p>
    <table>
        <thead><tr><th>Description</th><th>Days</th><th>Amount</th><th>Tax</th><th>Total</th></tr></thead>
        <tbody>
            @foreach ($invoice->lines as $line)
                <tr>
                    <td>{{ $line->description }}</td>
                    <td>{{ $line->billable_days }}</td>
                    <td>{{ number_format((float) $line->amount, 2) }}</td>
                    <td>{{ number_format((float) $line->tax_amount, 2) }}</td>
                    <td>{{ number_format((float) $line->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p><strong>Total: {{ number_format((float) $invoice->amount, 2) }}</strong></p>
    <p style="color:#666;font-size:.9rem">PDF rendering uses browser print. Tax authority e-invoicing is not configured in this environment.</p>
</body>
</html>
