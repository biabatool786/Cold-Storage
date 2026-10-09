@props(['name' => 'circle', 'class' => 'h-4 w-4 shrink-0'])

@php
    $paths = [
        'home' => 'M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3m10-11v10a1 1 0 01-1 1h-3m-6 0h6',
        'building' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H3m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5',
        'warehouse' => 'M3 21h18M5 21V9l7-5 7 5v12M9 21v-6h6v6',
        'users' => 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zm13 10v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75',
        'package' => 'M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16zM3.3 7L12 12l8.7-5M12 22V12',
        'calendar' => 'M8 7V3m8 4V3M3 11h18M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z',
        'truck' => 'M3 7h11v10H3V7zm11 3h4l3 3v4h-7V10zM7 20a1.5 1.5 0 100-3 1.5 1.5 0 000 3zm10 0a1.5 1.5 0 100-3 1.5 1.5 0 000 3z',
        'scale' => 'M12 3v18M5 8h14M7 8l-2 8h4l-2-8zm10 0l-2 8h4l-2-8z',
        'inbox' => 'M22 12h-6l-2 3h-4l-2-3H2M5.45 5.11L2 12v6a2 2 0 002 2h16a2 2 0 002-2v-6l-3.45-6.89A2 2 0 0016.76 4H7.24a2 2 0 00-1.79 1.11z',
        'layers' => 'M12 2l9 4.5-9 4.5L3 6.5 12 2zm0 9l9 4.5-9 4.5-9-4.5L12 11z',
        'send' => 'M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z',
        'thermometer' => 'M14 14.76V3.5a2.5 2.5 0 00-5 0v11.26a4.5 4.5 0 105 0z',
        'currency' => 'M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6',
        'wrench' => 'M14.7 6.3a4 4 0 015 5l-8.2 8.2a2.1 2.1 0 01-3 0l-1.4-1.4a2.1 2.1 0 010-3l8.2-8.2zM3 21l3-1',
        'shield' => 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z',
        'chart' => 'M3 3v18h18M7 14l4-4 4 4 5-6',
        'user' => 'M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z',
        'circle' => 'M12 22a10 10 0 100-20 10 10 0 000 20z',
        'chevron' => 'M9 6l6 6-6 6',
    ];
    $d = $paths[$name] ?? $paths['circle'];
@endphp
<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="{{ $d }}" />
</svg>
