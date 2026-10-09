<x-layouts.authenticated title="Permission matrix">
    <h2 class="text-2xl font-semibold tracking-tight">Permission matrix</h2>
    <p class="mt-1 text-sm text-muted">The six cold-storage roles stay fixed. This screen changes their grants.</p>
    @if (session('status'))<p class="mt-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    <form method="POST" action="{{ route('security.matrix.update') }}" class="mt-6 overflow-x-auto rounded-2xl border border-line bg-panel p-5">
        @csrf @method('PUT')
        @foreach ($roles as $role)
            @if ($role->name !== 'Super Admin')
                <input type="hidden" name="grants[{{ $role->id }}][]" value="">
            @endif
        @endforeach
        <table class="min-w-full text-left text-sm">
            <thead><tr><th class="py-2 pr-4">Permission</th>@foreach ($roles as $role)<th class="py-2 pr-4">{{ $role->name }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach ($permissions as $permission)
                    <tr>
                        <td class="py-1 pr-4">{{ $permission->name }}</td>
                        @foreach ($roles as $role)
                            <td class="py-1 pr-4">
                                @if ($role->name !== 'Super Admin')
                                    <input type="checkbox" name="grants[{{ $role->id }}][]" value="{{ $permission->name }}" @checked($role->permissions->contains('id', $permission->id))>
                                @else
                                    <span class="text-muted">fixed</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        <button class="btn-primary mt-4" type="submit">Save matrix</button>
    </form>
</x-layouts.authenticated>
