<x-layouts.authenticated :title="$tenant->exists ? 'Edit cold storage' : 'New cold storage'">
    <form method="POST" action="{{ $tenant->exists ? route('tenants.update', $tenant) : route('tenants.store') }}" class="max-w-xl space-y-4 rounded-2xl border border-line bg-panel p-5">
        @csrf
        @if ($tenant->exists)
            @method('PUT')
        @endif
        <h2 class="text-2xl font-semibold tracking-tight">{{ $tenant->exists ? 'Edit cold storage' : 'New cold storage' }}</h2>
        @foreach (['name' => 'Name', 'email' => 'Email', 'phone' => 'Phone', 'city' => 'City', 'country' => 'Country'] as $field => $label)
            <div>
                <label class="field-label" for="{{ $field }}">{{ $label }}</label>
                <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $tenant->{$field}) }}" class="field" @required(in_array($field, ['name', 'email'], true))>
                @error($field)<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
            </div>
        @endforeach
        <div>
            <label class="field-label" for="address">Address</label>
            <textarea id="address" name="address" class="field">{{ old('address', $tenant->address) }}</textarea>
        </div>
        <button class="btn-primary" type="submit">Save</button>
    </form>
</x-layouts.authenticated>
