@php
    use App\Support\Navigation;
    $account = auth()->user();
    $account?->loadMissing('roles', 'tenant');
    $modules = $account ? Navigation::modules($account) : [];
@endphp
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
<body class="bg-night font-sans text-ink antialiased">
    <input id="nav-toggle" type="checkbox" class="peer sr-only" aria-hidden="true">
    <label for="nav-toggle" class="fixed inset-0 z-30 hidden bg-ink/30 peer-checked:block lg:hidden" aria-hidden="true"></label>

    <x-sidebar :modules="$modules" />

    <div class="min-h-screen lg:pl-64">
        <header class="sticky top-0 z-20 flex items-center justify-between gap-3 border-b border-line bg-night/90 px-4 py-3 backdrop-blur sm:px-6">
            <div class="flex items-center gap-3">
                <label for="nav-toggle" class="btn-ghost cursor-pointer px-3 py-2 lg:hidden" aria-controls="app-sidebar">Menu</label>
                <div>
                    <p class="text-sm font-semibold">{{ $title ?? 'Dashboard' }}</p>
                    <p class="text-xs text-muted">{{ $account->tenant?->name ?? 'Platform' }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <div class="hidden text-right sm:block">
                    <p class="text-sm font-medium">{{ $account->name }}</p>
                    <p class="text-xs text-muted">{{ $account->roles->pluck('name')->join(', ') ?: 'No role' }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn-ghost px-3 py-2">Sign out</button>
                </form>
            </div>
        </header>

        <main class="px-4 py-6 sm:px-6">
            @if (session('status'))
                <p class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</p>
            @endif
            {{ $slot }}
        </main>
    </div>
    @livewireScripts
</body>
</html>
