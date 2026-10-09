<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Gate pass {{ $pass->code }}</title>
<style>body{font-family:Georgia,serif;margin:2rem}h1{margin:0}.muted{color:#555}@media print{.no-print{display:none}}</style></head>
<body>
<button class="no-print" onclick="window.print()">Print</button>
<h1>Gate pass</h1>
<p class="muted">{{ $pass->code }} · {{ $pass->status }}</p>
<p>DO {{ $order?->number ?? '#'.$pass->delivery_order_id }}</p>
<p>Customer: {{ $order?->customer?->name }}</p>
<p>Lot: {{ $order?->lot?->lot_number }} · Qty {{ $order?->picked_qty ?? $order?->quantity }}</p>
<p>Issued {{ $pass->created_at }}</p>
</body></html>
