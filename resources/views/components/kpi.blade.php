@props(['label', 'value', 'hint' => null])
<div {{ $attributes->merge(['class' => 'rounded-2xl border border-line bg-panel p-4']) }}>
    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-muted">{{ $label }}</p>
    <p class="mt-2 text-2xl font-semibold tracking-tight">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-muted">{{ $hint }}</p>
    @endif
</div>
