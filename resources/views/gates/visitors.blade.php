<x-layouts.authenticated title="Visitors">
    <div class="mb-6"><h2 class="text-2xl font-semibold tracking-tight">Visitors and contractors</h2></div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($gate->canVisit(auth()->user()) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('gates.visitors.store', $branches->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <select class="field md:col-span-2" onchange="this.form.action = this.value">@foreach ($branches as $branch)<option value="{{ route('gates.visitors.store', $branch) }}">{{ $branch->name }}</option>@endforeach</select>
            <input name="name" required placeholder="Name" class="field">
            <input name="company" placeholder="Company" class="field">
            <input name="purpose" placeholder="Purpose" class="field">
            <input name="vehicle_no" placeholder="Vehicle" class="field">
            <div class="md:col-span-2"><button class="btn-primary" type="submit">Register</button></div>
        </form>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <tbody class="divide-y divide-line">
                @forelse ($visitors as $visitor)
                    <tr><td class="px-4 py-3">{{ $visitor->name }}</td><td class="px-4 py-3">{{ $visitor->company ?? '—' }}</td><td class="px-4 py-3">{{ $visitor->purpose ?? '—' }}</td></tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted">No visitors.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
