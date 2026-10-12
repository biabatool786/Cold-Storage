<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Statement {{ $customer->name }}</title>
<style>body{font-family:Georgia,serif;margin:2rem}table{width:100%;border-collapse:collapse}td,th{border-bottom:1px solid #ccc;padding:.35rem;text-align:left;font-size:.9rem}</style>
</head><body>
<h1>Customer statement — {{ $customer->name }}</h1>
<table><thead><tr><th>Date</th><th>Type</th><th>Ref</th><th>Debit</th><th>Credit</th><th>Balance</th></tr></thead>
<tbody>
@foreach ($statement['lines'] as $line)
<tr>
<td>{{ $line['date'] }}</td><td>{{ $line['type'] }}</td><td>{{ $line['reference'] }}</td>
<td>{{ number_format($line['debit'], 2) }}</td><td>{{ number_format($line['credit'], 2) }}</td><td>{{ number_format($line['balance'], 2) }}</td>
</tr>
@endforeach
</tbody></table>
<p>Outstanding: {{ number_format($statement['closing'], 2) }}</p>
<script>window.print()</script>
</body></html>
