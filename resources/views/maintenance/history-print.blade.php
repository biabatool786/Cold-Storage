<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>History {{ $asset->asset_code }}</title>
<style>body{font-family:Georgia,serif;margin:2rem}</style></head><body>
<h1>Downtime &amp; cost — {{ $asset->asset_code }}</h1>
<p>Downtime hours: {{ number_format($history['downtime_hours'], 2) }}</p>
<p>Tickets: {{ $history['tickets'] }}</p>
<p>Work orders: {{ $history['work_orders'] }}</p>
<p>Actual maintenance cost: {{ number_format($history['actual_cost'], 2) }}</p>
<p>Parts issues: {{ $history['parts_issues'] }}</p>
<script>window.print()</script>
</body></html>
