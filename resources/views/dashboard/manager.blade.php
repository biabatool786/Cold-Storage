<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($dashboard['stats'] as $stat)
        <x-kpi :label="$stat['label']" :value="$stat['value']" :hint="$stat['hint']" />
    @endforeach
</div>
