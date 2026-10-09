<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $receipt->number ?? 'GRN' }}</title>
    <style>
        body { font-family: Georgia, 'Times New Roman', serif; margin: 2rem; color: #111; }
        h1 { font-size: 1.6rem; margin: 0 0 .25rem; }
        .muted { color: #555; font-size: .9rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1.5rem; }
        th, td { border-bottom: 1px solid #ccc; padding: .5rem; text-align: left; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Print</button>
    <h1>Goods Receipt Note</h1>
    <p class="muted">{{ $receipt->number ?? '#'.$receipt->id }} · {{ $receipt->status }}</p>
    <table>
        <tr><th>Customer</th><td>{{ $receipt->customer?->name }}</td></tr>
        <tr><th>Product</th><td>{{ $receipt->commodity?->name }}</td></tr>
        <tr><th>Vehicle</th><td>{{ $receipt->gateEntry?->vehicle_no }}</td></tr>
        <tr><th>Booking</th><td>{{ $receipt->booking_id ? '#'.$receipt->booking_id : '—' }}</td></tr>
        <tr><th>Gate</th><td>#{{ $receipt->gate_entry_id }}</td></tr>
        <tr><th>Weighment</th><td>
            @if ($receipt->weighment)
                Gross {{ $receipt->weighment->gross_weight }} · Tare {{ $receipt->weighment->tare_weight }} · Net {{ $receipt->weighment->net_weight }}
            @else
                —
            @endif
        </td></tr>
        <tr><th>Received qty</th><td>{{ $receipt->quantity }}</td></tr>
        <tr><th>Accepted qty</th><td>{{ $receipt->accepted_qty ?? '—' }}</td></tr>
        <tr><th>Outcome</th><td>{{ $receipt->outcome ?? '—' }}</td></tr>
        <tr><th>Grade / Temp</th><td>{{ $receipt->grade ?? '—' }} / {{ $receipt->temperature ?? '—' }}</td></tr>
        <tr><th>Lot</th><td>{{ $receipt->lot?->lot_number ?? '—' }}</td></tr>
    </table>
</body>
</html>
