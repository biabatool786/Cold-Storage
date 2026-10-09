@php
    use App\Support\Navigation;
    $account = auth()->user();
    $account?->loadMissing('roles', 'tenant');
    $navigation = $account ? Navigation::groups($account) : [];
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
    <input id="nav-toggle" type="checkbox" class="peer sr-only">
    <label for="nav-toggle" class="fixed inset-0 z-30 hidden bg-ink/30 peer-checked:block lg:hidden"></label>
    <aside class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r border-line bg-panel transition peer-checked:translate-x-0 lg:translate-x-0">
        <div class="flex items-center gap-3 px-5 py-5">
            <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-accent text-sm font-semibold text-white">CS</div>
            <div>
                <p class="text-sm font-semibold tracking-tight">Cold Storage</p>
                <p class="text-xs text-muted">Management</p>
            </div>
        </div>
        <nav class="flex-1 space-y-6 overflow-y-auto px-3 pb-6" aria-label="Primary">
            @foreach ($navigation as $group)
                <div>
                    <p class="px-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-muted">{{ $group['label'] }}</p>
                    <div class="mt-2 space-y-1">
                        @foreach ($group['items'] as $item)
                            <a
                                href="{{ $item['href'] }}"
                                @class([
                                    'block rounded-xl px-3 py-2 text-sm',
                                    'bg-teal-50 font-medium text-accent' => $item['active'],
                                    'text-ink hover:bg-panel-2' => ! $item['active'],
                                ])
                            >{{ $item['label'] }}</a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>
    </aside>

    <div class="lg:pl-64">
        <header class="sticky top-0 z-20 flex items-center justify-between gap-3 border-b border-line bg-night/90 px-4 py-3 backdrop-blur sm:px-6">
            <div class="flex items-center gap-3">
                <label for="nav-toggle" class="btn-ghost px-3 py-2 lg:hidden">Menu</label>
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
