<x-layouts.authenticated title="Template Preview">
    <h2 class="text-2xl font-semibold tracking-tight mb-4">Preview: {{ $template->name }}</h2>
    <div class="rounded-2xl border border-line bg-panel p-5 text-sm space-y-2">
        @if($preview['subject'])<p><strong>Subject:</strong> {{ $preview['subject'] }}</p>@endif
        <div>{!! nl2br($preview['body']) !!}</div>
    </div>
    <a class="btn-secondary mt-4 inline-block" href="{{ route('notifications.templates') }}">Back</a>
</x-layouts.authenticated>
