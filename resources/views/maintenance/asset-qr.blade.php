<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>QR {{ $asset->asset_code }}</title>
<style>body{font-family:Georgia,serif;text-align:center;margin:2rem}code{font-size:1.2rem}</style>
</head><body>
<h1>{{ $asset->asset_code }}</h1>
<p>{{ $asset->displayName() }}</p>
{{-- Payload is opaque asset reference only — no tenant secrets --}}
<pre style="display:inline-block;border:2px solid #111;padding:1.5rem;letter-spacing:.2em;font-size:1.4rem">CSMS-ASSET:{{ $asset->id }}:{{ $asset->asset_code }}</pre>
<p style="margin-top:1rem;font-size:.85rem;color:#555">Scan / look up by asset code. Tenant identifiers are not printed.</p>
<script>window.print()</script>
</body></html>
