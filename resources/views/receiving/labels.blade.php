<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Lot labels</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; margin: 1.5rem; }
        .label { border: 2px solid #111; padding: 1.25rem; width: 280px; margin-bottom: 1rem; }
        .lot { font-size: 1.4rem; font-weight: 700; letter-spacing: .04em; }
        .barcode { font-family: 'Courier New', monospace; font-size: 1.1rem; letter-spacing: .2em; margin: .75rem 0; }
        .qr { width: 96px; height: 96px; border: 1px solid #333; display: grid; place-items: center; font-size: .65rem; word-break: break-all; padding: .35rem; }
        .muted { color: #444; font-size: .85rem; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Print labels</button>
    @if ($lot)
        <div class="label">
            <div class="lot">{{ $lot->lot_number }}</div>
            <div class="barcode">*{{ $lot->barcode ?? $lot->lot_number }}*</div>
            <div class="qr">{{ $lot->lot_number }}</div>
            <p class="muted">GRN {{ $receipt->number ?? '#'.$receipt->id }} · Qty {{ $lot->quantity }}</p>
        </div>
    @else
        <p>No lot generated yet. Approve the GRN first.</p>
    @endif
</body>
</html>
