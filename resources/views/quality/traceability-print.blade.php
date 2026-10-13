<!DOCTYPE html>
<html><head><title>Lot Traceability</title><style>body{font-family:sans-serif;padding:24px} h1{font-size:18px}</style></head>
<body onload="window.print()">
    <h1>Lot {{ $trace['lot']->lot_number }} — Traceability</h1>
    <h2>Backward</h2>
    <p>GRN: {{ $trace['backward']['goods_receipt']['number'] ?? 'Unavailable' }}</p>
    <p>Customer: {{ $trace['backward']['customer'] ?? '—' }}</p>
    <h2>Forward</h2>
    <ul>@foreach($trace['forward']['movements'] as $m)<li>{{ $m['type'] }} {{ $m['quantity'] }}</li>@endforeach</ul>
    <ul>@foreach($trace['forward']['dispatches'] as $d)<li>{{ $d['number'] }} {{ $d['status'] }}</li>@endforeach</ul>
    @foreach($trace['unavailable'] as $u)<p><em>{{ $u }}</em></p>@endforeach
</body></html>
