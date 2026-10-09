<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f766e">
    <title>{{ $title ?? config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-night font-sans text-ink antialiased">
    <div class="grid min-h-dvh lg:grid-cols-2">
        <div class="relative hidden overflow-hidden bg-panel p-12 lg:flex lg:flex-col lg:justify-between">
            <div class="absolute -left-20 -top-20 h-72 w-72 rounded-full bg-accent/10 blur-3xl"></div>
            <div class="absolute bottom-0 right-0 h-80 w-80 rounded-full bg-amber-200/40 blur-3xl"></div>
            <div class="relative">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-accent text-lg font-semibold text-white shadow-[0_10px_24px_rgb(15_118_110_/_0.25)]">CS</div>
                <h2 class="mt-8 text-4xl font-semibold tracking-tight">Cold Storage</h2>
                <p class="mt-3 max-w-md text-muted">Chambers, stock, receiving, and monitoring for each cold store, kept apart by tenant.</p>
                <div class="mt-10 grid max-w-md gap-3">
                    <div class="flex items-center gap-3 rounded-2xl border border-line bg-night px-4 py-3">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-teal-100 text-sm font-semibold text-accent">01</span>
                        <div>
                            <div class="text-sm font-medium">Chambers and stock</div>
                            <div class="text-xs text-muted">See what is stored, and where.</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 rounded-2xl border border-line bg-night px-4 py-3">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-100 text-sm font-semibold text-emerald-700">02</span>
                        <div>
                            <div class="text-sm font-medium">Receiving and dispatch</div>
                            <div class="text-xs text-muted">Inbound and outbound lots stay with the tenant.</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 rounded-2xl border border-line bg-night px-4 py-3">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-amber-100 text-sm font-semibold text-amber-700">03</span>
                        <div>
                            <div class="text-sm font-medium">Quality and temperature</div>
                            <div class="text-xs text-muted">Inspections and chamber readings in one place.</div>
                        </div>
                    </div>
                </div>
            </div>
            <p class="relative text-sm text-muted">Multi-tenant · Chambers · Stock · Monitoring</p>
        </div>
        <div class="flex items-center justify-center px-5 py-10 sm:px-6 sm:py-12">
            <div class="w-full max-w-md">
                <div class="mb-8 lg:hidden">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-accent font-semibold text-white">CS</div>
                    <h1 class="mt-4 text-3xl font-semibold tracking-tight">Cold Storage</h1>
                </div>
                {{ $slot }}
            </div>
        </div>
    </div>
    @livewireScripts
</body>
</html>
