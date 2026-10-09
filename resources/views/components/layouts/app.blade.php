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
    <div class="mx-auto flex min-h-screen max-w-5xl flex-col px-6 py-10">
        <header class="flex items-center justify-between border-b border-line pb-6">
            <div>
                <p class="text-xs font-semibold tracking-[0.16em] text-accent">COLD STORAGE</p>
                <h1 class="mt-1 text-2xl font-semibold">{{ config('app.name') }}</h1>
            </div>
            <a href="{{ route('login') }}" class="btn-primary">Sign in</a>
        </header>
        <main class="flex-1 py-10">
            {{ $slot }}
        </main>
    </div>
    @livewireScripts
</body>
</html>
