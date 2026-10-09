<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Dispatch note</title>
<style>body{font-family:Georgia,serif;margin:2rem}table{width:100%;border-collapse:collapse;margin-top:1rem}th,td{border-bottom:1px solid #ccc;padding:.45rem;text-align:left}@media print{.no-print{display:none}}</style></head>
<body>
<button class="no-print" onclick="window.print()">Print</button>
<h1>Dispatch note</h1>
<p>{{ $order->number }} · {{ $order->status }}</p>
<table>
<tr><th>Customer</th><td>{{ $order->customer?->name }}</td></tr>
<tr><th>Quantity</th><td>{{ $order->quantity }} (picked {{ $order->picked_qty }})</td></tr>
<tr><th>Temperature</th><td>{{ $order->outgoing_temperature ?? '—' }}</td></tr>
<tr><th>Loaders</th><td>{{ $order->loader_count ?? '—' }} {{ $order->loader_notes }}</td></tr>
<tr><th>Gate pass</th><td>{{ $order->pass?->code ?? '—' }}</td></tr>
</table>
@if($order->pickLines->isNotEmpty())
<h2>Pick lines</h2>
<table><thead><tr><th>Lot</th><th>Slot</th><th>Picked</th></tr></thead>
<tbody>@foreach($order->pickLines as $line)<tr><td>{{ $line->lot?->lot_number }}</td><td>{{ $line->slot?->code }}</td><td>{{ $line->picked_qty }}</td></tr>@endforeach</tbody></table>
@endif
</body></html>
