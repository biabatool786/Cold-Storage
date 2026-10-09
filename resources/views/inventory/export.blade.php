<x-layouts.authenticated title="Stock export">
    <div class="mb-6"><h2 class="text-2xl font-semibold tracking-tight">Stock statement export</h2>
        @if ($supervisor)<p class="mt-1 text-sm text-muted">Supervisors must export a single assigned chamber.</p>@endif
    </div>
    <form method="GET" class="mb-6 flex flex-wrap gap-2 rounded-2xl border border-line bg-panel p-5">
        <select name="chamber_id" class="field w-auto" @if($supervisor) required @endif>
            @unless($supervisor)<option value="">All chambers</option>@endunless
            @foreach ($chambers as $ch)
                <option value="{{ $ch->id }}" @selected((int)$chamberId === (int)$ch->id)>{{ $ch->code }} — {{ $ch->name }}</option>
            @endforeach
        </select>
        <button class="btn-ghost" type="submit">Preview</button>
        <button class="btn-primary" name="download" value="1" type="submit">Download CSV</button>
    </form>
    <p class="text-sm text-muted">{{ $lots->count() }} lot(s) in statement.</p>
</x-layouts.authenticated>
