<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Receivable aging</title>
<style>body{font-family:Georgia,serif;margin:2rem}table{width:100%;border-collapse:collapse}td,th{border-bottom:1px solid #ccc;padding:.35rem;text-align:left}</style>
</head><body>
<h1>Receivable aging</h1>
<table><thead><tr><th>Customer</th><th>Current</th><th>1–30</th><th>31–60</th><th>61–90</th><th>90+</th><th>Total</th></tr></thead>
<tbody>
@foreach ($rows as $row)
<tr>
<td>{{ $row['customer'] }}</td>
<td>{{ number_format($row['current'], 2) }}</td><td>{{ number_format($row['d30'], 2) }}</td>
<td>{{ number_format($row['d60'], 2) }}</td><td>{{ number_format($row['d90'], 2) }}</td>
<td>{{ number_format($row['d90_plus'], 2) }}</td><td>{{ number_format($row['total'], 2) }}</td>
</tr>
@endforeach
</tbody></table>
<script>window.print()</script>
</body></html>
