<section id="{{ $section['id'] }}" class="rounded-2xl border border-line bg-panel p-5">
    <h3 class="text-lg font-semibold">{{ $section['title'] }}</h3>
    <p class="mt-1 text-sm text-muted">{{ $section['note'] }}</p>
    <div class="mt-4 overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted">
                <tr>
                    @foreach ($section['columns'] as $column)
                        <th class="py-2 pr-4 font-medium">{{ $column }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="{{ count($section['columns']) }}" class="py-8 text-muted">No records yet.</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>
