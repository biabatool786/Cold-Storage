<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>P&L</title>
<style>body{font-family:Georgia,serif;margin:2rem}</style></head><body>
<h1>P&amp;L — {{ $branch->name }} ({{ $start }} to {{ $end }})</h1>
<p>Revenue: {{ number_format($report['revenue'], 2) }}</p>
<p>Expenses: {{ number_format($report['expenses'], 2) }}</p>
<p>Profit: {{ number_format($report['profit'], 2) }}</p>
@foreach ($report['notes'] as $note)<p>{{ $note }}</p>@endforeach
<script>window.print()</script>
</body></html>
