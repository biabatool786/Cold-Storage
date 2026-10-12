<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Day book</title>
<style>body{font-family:Georgia,serif;margin:2rem}table{width:100%;border-collapse:collapse}td,th{border-bottom:1px solid #ccc;padding:.35rem;text-align:left}</style>
</head><body>
<h1>Day book — {{ $branch->name }} ({{ $start }} to {{ $end }})</h1>
<table><thead><tr><th>Date</th><th>Type</th><th>Ref</th><th>Debit</th><th>Credit</th></tr></thead><tbody>
@foreach ($lines as $line)
<tr><td>{{ $line['date'] }}</td><td>{{ $line['type'] }}</td><td>{{ $line['reference'] }}</td>
<td>{{ number_format($line['debit'], 2) }}</td><td>{{ number_format($line['credit'], 2) }}</td></tr>
@endforeach
</tbody></table>
<script>window.print()</script>
</body></html>
