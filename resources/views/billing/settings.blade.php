<x-layouts.authenticated title="Billing settings">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Charge types, tax &amp; grace</h2>
        <p class="mt-1 text-sm text-muted">Branch defaults for tax, free days, grace periods, demurrage and late fees.</p>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @if ($settings && $branches->isNotEmpty())
        <form method="POST" action="{{ route('billing.settings.update', $branches->first()) }}" class="grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <select class="field md:col-span-2" onchange="this.form.action = this.value" @disabled(! $desk->canConfigureSettings(auth()->user()))>
                @foreach ($branches as $branch)
                    <option value="{{ route('billing.settings.update', $branch) }}">{{ $branch->name }}</option>
                @endforeach
            </select>
            <input name="default_tax_percent" type="number" step="0.01" value="{{ $settings->default_tax_percent }}" class="field" @disabled(! $desk->canConfigureSettings(auth()->user()))>
            <input name="default_free_days" type="number" value="{{ $settings->default_free_days }}" class="field" @disabled(! $desk->canConfigureSettings(auth()->user()))>
            <input name="default_grace_days" type="number" value="{{ $settings->default_grace_days }}" class="field" @disabled(! $desk->canConfigureSettings(auth()->user()))>
            <input name="demurrage_rate" type="number" step="0.01" value="{{ $settings->demurrage_rate }}" class="field" @disabled(! $desk->canConfigureSettings(auth()->user()))>
            <input name="late_fee_percent" type="number" step="0.01" value="{{ $settings->late_fee_percent }}" class="field" @disabled(! $desk->canConfigureSettings(auth()->user()))>
            @if ($desk->canConfigureSettings(auth()->user()))
                <div class="md:col-span-2"><button class="btn-primary" type="submit">Save settings</button></div>
            @endif
        </form>
    @endif
</x-layouts.authenticated>
