@props(['modules' => []])

@php
    $openKeys = collect($modules)->filter(fn ($m) => $m['open'])->pluck('key')->values()->all();
@endphp

<aside
    id="app-sidebar"
    class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r border-line bg-panel transition-transform duration-200 ease-out peer-checked:translate-x-0 lg:translate-x-0"
    data-open-keys='@json($openKeys)'
>
    <div class="flex items-center gap-3 border-b border-line px-4 py-4">
        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-accent text-xs font-semibold text-white">CS</div>
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold tracking-tight">Cold Storage</p>
            <p class="truncate text-[11px] text-muted">Management System</p>
        </div>
    </div>

    <nav class="sidebar-nav flex-1 space-y-1 overflow-y-auto px-2 py-3" aria-label="Primary">
        @foreach ($modules as $module)
            @if ($module['type'] === 'link')
                <a
                    href="{{ $module['href'] }}"
                    @class([
                        'sidebar-link flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm transition',
                        'bg-teal-50 font-medium text-accent' => $module['active'],
                        'text-ink hover:bg-panel-2' => ! $module['active'],
                    ])
                >
                    <x-nav-icon :name="$module['icon']" class="h-4 w-4 shrink-0 opacity-80" />
                    <span class="truncate">{{ $module['label'] }}</span>
                </a>
            @else
                <div
                    @class(['sidebar-module', 'is-open' => $module['open']])
                    data-module-key="{{ $module['key'] }}"
                    data-force-open="{{ $module['open'] ? '1' : '0' }}"
                >
                    <button
                        type="button"
                        class="sidebar-module-toggle flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-sm transition hover:bg-panel-2"
                        aria-expanded="{{ $module['open'] ? 'true' : 'false' }}"
                        aria-controls="nav-panel-{{ $module['key'] }}"
                    >
                        <x-nav-icon :name="$module['icon']" @class([
                            'h-4 w-4 shrink-0',
                            'text-accent' => $module['active'],
                            'opacity-80' => ! $module['active'],
                        ]) />
                        <span @class([
                            'min-w-0 flex-1 truncate font-medium',
                            'text-accent' => $module['active'],
                            'text-ink' => ! $module['active'],
                        ])>{{ $module['label'] }}</span>
                        <x-nav-icon name="chevron" class="sidebar-chevron h-3.5 w-3.5 shrink-0 text-muted transition-transform duration-200" />
                    </button>
                    <div id="nav-panel-{{ $module['key'] }}" class="sidebar-panel" aria-hidden="{{ $module['open'] ? 'false' : 'true' }}">
                        <div class="mb-1 ml-3 space-y-0.5 border-l border-line pl-2.5">
                            @foreach ($module['items'] as $item)
                                <a
                                    href="{{ $item['href'] }}"
                                    @class([
                                        'block rounded-md px-2.5 py-1.5 text-[13px] transition',
                                        'bg-teal-50 font-medium text-accent' => $item['active'],
                                        'text-muted hover:bg-panel-2 hover:text-ink' => ! $item['active'],
                                    ])
                                    @if (! $module['open']) tabindex="-1" @endif
                                >{{ $item['label'] }}</a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    </nav>
</aside>
