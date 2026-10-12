<x-layouts.authenticated title="{{ $asset ? 'Edit asset' : 'New asset' }}">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">{{ $asset ? 'Edit asset' : 'New asset' }}</h2>
    </div>
    <form method="POST" action="{{ $asset ? route('assets.update', $asset) : route('assets.store', $branches->first()) }}" class="grid max-w-3xl gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
        @csrf
        @if ($asset) @method('PUT') @endif
        @unless ($asset)
            <label class="text-sm md:col-span-2">Branch
                <select name="branch_picker" class="field mt-1" onchange="this.form.action='{{ url('/branches') }}/'+this.value+'/assets'">
                    @foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected($loop->first)>{{ $branch->name }}</option>@endforeach
                </select>
            </label>
        @endunless
        <label class="text-sm">Asset code<input name="asset_code" required value="{{ old('asset_code', $asset->asset_code ?? '') }}" class="field mt-1"></label>
        <label class="text-sm">Name<input name="name" required value="{{ old('name', $asset->name ?? $asset->title ?? '') }}" class="field mt-1"></label>
        <label class="text-sm">Category<input name="category" value="{{ old('category', $asset->category ?? '') }}" class="field mt-1"></label>
        <label class="text-sm">Location<input name="location" value="{{ old('location', $asset->location ?? '') }}" class="field mt-1"></label>
        <label class="text-sm">Serial<input name="serial_number" value="{{ old('serial_number', $asset->serial_number ?? '') }}" class="field mt-1"></label>
        <label class="text-sm">Installed<input name="installed_at" type="date" value="{{ old('installed_at', optional($asset->installed_at ?? null)->toDateString()) }}" class="field mt-1"></label>
        <label class="text-sm">Status
            <select name="operational_status" class="field mt-1">
                @foreach (['active','down','retired'] as $st)
                    <option value="{{ $st }}" @selected(old('operational_status', $asset->operational_status ?? 'active') === $st)>{{ $st }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">Purchase cost<input name="purchase_cost" type="number" step="0.01" value="{{ old('purchase_cost', $asset->purchase_cost ?? '') }}" class="field mt-1"></label>
        <label class="text-sm md:col-span-2">Notes<textarea name="detail" class="field mt-1" rows="2">{{ old('detail', $asset->detail ?? '') }}</textarea></label>
        <div class="md:col-span-2"><button class="btn-primary" type="submit">Save</button></div>
    </form>
</x-layouts.authenticated>
