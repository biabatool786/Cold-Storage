<x-layouts.authenticated title="Settings">
    <form method="POST" action="{{ route('settings.tenant.update') }}" class="max-w-xl space-y-4 rounded-2xl border border-line bg-panel p-5">
        @csrf
        @method('PUT')
        <h2 class="text-2xl font-semibold tracking-tight">Cold storage settings</h2>
        <p class="text-sm text-muted">These details belong to your tenant. Subscription and platform settings stay with the Super Admin.</p>
        @foreach (['name' => 'Name', 'phone' => 'Phone', 'city' => 'City', 'country' => 'Country'] as $field => $label)
            <div>
                <label class="field-label" for="{{ $field }}">{{ $label }}</label>
                <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $tenant->{$field}) }}" class="field" @required($field === 'name')>
            </div>
        @endforeach
        <div>
            <label class="field-label" for="address">Address</label>
            <textarea id="address" name="address" class="field">{{ old('address', $tenant->address) }}</textarea>
        </div>
        <button class="btn-primary" type="submit">Save settings</button>
    </form>
</x-layouts.authenticated>
