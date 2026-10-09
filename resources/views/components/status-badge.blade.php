@props(['status'])
@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $tone = match ($value) {
        'ACTIVE', 'TRIAL' => 'bg-emerald-100 text-emerald-800',
        'SUSPENDED', 'PAST_DUE' => 'bg-amber-100 text-amber-800',
        'CANCELLED', 'EXPIRED', 'INACTIVE' => 'bg-stone-200 text-stone-700',
        default => 'bg-stone-100 text-stone-600',
    };
@endphp
<span {{ $attributes->merge(['class' => "inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {$tone}"]) }}>{{ str_replace('_', ' ', $value) }}</span>
