<x-layouts.authenticated title="Blocked list">
    <div class="mb-6"><h2 class="text-2xl font-semibold tracking-tight">Blocked vehicles and drivers</h2></div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($gate->canBlock(auth()->user()) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('gates.blocked.store', $branches->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <select class="field md:col-span-2" onchange="this.form.action = this.value">@foreach ($branches as $branch)<option value="{{ route('gates.blocked.store', $branch) }}">{{ $branch->name }}</option>@endforeach</select>
            <input name="vehicle_no" placeholder="Vehicle number" class="field">
            <input name="driver_name" placeholder="Driver" class="field">
            <input name="reason" required placeholder="Reason" class="field md:col-span-2">
            <div class="md:col-span-2"><button class="btn-primary" type="submit">Add block</button></div>
        </form>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <tbody class="divide-y divide-line">
                @forelse ($blocks as $block)
                    <tr><td class="px-4 py-3">{{ $block->vehicle_no ?? '—' }}</td><td class="px-4 py-3">{{ $block->driver_name ?? '—' }}</td><td class="px-4 py-3">{{ $block->reason }}</td></tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted">No blocked records.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
