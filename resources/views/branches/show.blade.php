<x-layouts.authenticated :title="$branch->name">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ $branch->name }}</h2>
        <p class="mt-1 text-sm text-muted">{{ $branch->code }} · {{ $branch->status->value }}</p>
    </div>

    @if ($canAssign)
        <form method="POST" action="{{ route('branches.assign', $branch) }}" class="grid gap-4 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <div>
                <label for="user_id" class="field-label">Assign user</label>
                <select id="user_id" name="user_id" class="field">
                    @foreach ($candidates as $candidate)
                        <option value="{{ $candidate->id }}">{{ $candidate->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button class="btn-primary" type="submit">Save access</button>
            </div>
        </form>
    @endif

    <div class="mt-6 overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-4 py-3 font-medium">Assigned users</th>
                    <th class="px-4 py-3 font-medium">Email</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($branch->users as $member)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $member->name }}</td>
                        <td class="px-4 py-3 text-muted">{{ $member->email }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="px-4 py-8 text-muted">No users assigned.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
