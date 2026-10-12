<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Energy report</title>
<style>body{font-family:Georgia,serif;margin:2rem}table{border-collapse:collapse;width:100%}td,th{border:1px solid #ccc;padding:.5rem;text-align:left}</style>
</head><body>
<h1>Consumption &amp; cost — {{ $branch->name }}</h1>
<p>{{ $from }} to {{ $to }}@if($chamber) · {{ $chamber->name }}@endif</p>
<table>
    <tr><th>kWh</th><td>{{ number_format($report['kwh'], 3) }}</td></tr>
    <tr><th>Energy cost</th><td>{{ $report['energy_cost'] !== null ? number_format($report['energy_cost'], 2) : 'Unavailable' }}</td></tr>
    <tr><th>Tonnage</th><td>{{ $report['tonnage'] !== null ? number_format($report['tonnage'], 3) : 'Unavailable' }}</td></tr>
    <tr><th>Cost / ton</th><td>{{ is_numeric($report['cost_per_ton']) ? number_format($report['cost_per_ton'], 2) : $report['cost_per_ton'] }}</td></tr>
</table>
<p style="font-size:.85rem;margin-top:1rem">Sources: {{ implode('; ', $report['sources']) }}</p>
<script>window.print()</script>
</body></html>
