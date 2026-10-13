<x-layouts.authenticated title="Alert Preferences">
    <h2 class="text-2xl font-semibold tracking-tight mb-6">Role alert preferences</h2>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    <form method="GET" class="mb-4">
        <select name="role" class="field" onchange="this.form.submit()">
            @foreach($roles as $r)
                <option value="{{ $r }}" @selected($role === $r)>{{ $r }}</option>
            @endforeach
        </select>
    </form>
    <form method="POST" action="{{ route('notifications.preferences.store') }}" class="rounded-2xl border border-line bg-panel p-5 space-y-3">
        @csrf
        <input type="hidden" name="role_name" value="{{ $role }}">
        @foreach($categories as $cat)
            @php
                $defaultRoles = $defaults[$cat] ?? [];
                $pref = $prefs->get($cat);
                $enabled = $pref ? (bool) $pref->enabled : in_array($role, $defaultRoles, true);
            @endphp
            <label class="flex items-center justify-between gap-3 text-sm border-b border-line py-2">
                <span>{{ $cat }}</span>
                <span class="flex items-center gap-2">
                    <input type="hidden" name="categories[{{ $cat }}][channels][]" value="in_app">
                    <input type="checkbox" name="categories[{{ $cat }}][enabled]" value="1" @checked($enabled)> Enabled
                </span>
            </label>
        @endforeach
        <button class="btn-primary" type="submit">Save preferences</button>
    </form>
</x-layouts.authenticated>
