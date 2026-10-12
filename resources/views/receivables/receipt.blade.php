<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Receipt #{{ $payment->id }}</title>
<style>body{font-family:Georgia,serif;margin:2rem;color:#111}h1{font-size:1.4rem}table{width:100%;border-collapse:collapse;margin-top:1rem}td,th{border-bottom:1px solid #ddd;padding:.4rem;text-align:left}</style>
</head><body>
<h1>Payment receipt #{{ $payment->id }}</h1>
<p>{{ $payment->customer?->name }} · {{ optional($payment->paid_on)->toDateString() }} · {{ strtoupper($payment->method) }}</p>
<p>Amount: <strong>{{ number_format((float) $payment->amount, 2) }}</strong> · Ref: {{ $payment->reference ?: '—' }}</p>
<table><thead><tr><th>Invoice</th><th>Allocated</th></tr></thead><tbody>
@forelse ($payment->allocations as $alloc)
<tr><td>{{ $alloc->invoice?->number ?? '#'.$alloc->storage_invoice_id }}</td><td>{{ number_format((float) $alloc->amount, 2) }}</td></tr>
@empty
<tr><td colspan="2">Unallocated / deposit</td></tr>
@endforelse
</tbody></table>
<script>window.print()</script>
</body></html>
