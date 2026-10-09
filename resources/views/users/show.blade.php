<x-layouts.authenticated :title="$account->name">
    <h2 class="text-2xl font-semibold tracking-tight">{{ $account->name }}</h2>
    <p class="mt-1 text-sm text-muted">{{ $account->email }} · {{ $account->roles->pluck('name')->join(', ') }} · {{ $account->status->value }}</p>
    @if (session('status'))<p class="mt-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($errors->any())<p class="mt-4 text-sm text-rose-700">{{ $errors->first() }}</p>@endif

    @if ($canEdit)
        <form method="POST" action="{{ route('users.update', $account) }}" class="mt-6 grid max-w-xl gap-3 rounded-2xl border border-line bg-panel p-5">
            @csrf @method('PUT')
            <input name="name" value="{{ $account->name }}" required class="field">
            <input name="email" type="email" value="{{ $account->email }}" required class="field">
            <input name="phone" value="{{ $account->phone }}" placeholder="Phone" class="field">
            <button class="btn-primary" type="submit">Save profile</button>
        </form>
        <form method="POST" action="{{ route('users.status', $account) }}" class="mt-4 flex max-w-xl gap-2">
            @csrf
            <select name="status" class="field w-auto">
                <option value="ACTIVE" @selected($account->status->value === 'ACTIVE')>Active</option>
                <option value="INACTIVE" @selected($account->status->value === 'INACTIVE')>Inactive</option>
            </select>
            <button class="btn-ghost" type="submit">Update status</button>
        </form>
        <form method="POST" action="{{ route('users.password', $account) }}" class="mt-4 grid max-w-xl gap-2 rounded-2xl border border-line bg-panel p-5">
            @csrf
            <input name="password" type="password" required placeholder="New password" class="field">
            <input name="password_confirmation" type="password" required placeholder="Confirm password" class="field">
            <button class="btn-ghost" type="submit">Reset password</button>
        </form>
    @endif
    @if ($canExecute)
        <div class="mt-4 flex gap-2">
            <form method="POST" action="{{ route('users.unlock', $account) }}">@csrf<button class="btn-ghost" type="submit">Unlock</button></form>
            <form method="POST" action="{{ route('users.logout', $account) }}">@csrf<button class="btn-ghost" type="submit">Force logout</button></form>
        </div>
        @if ($branches->isNotEmpty())
        <form method="POST" action="{{ route('branches.assign', $branches->first()) }}" class="mt-4 max-w-xl rounded-2xl border border-line bg-panel p-5">
            @csrf
            <input type="hidden" name="user_id" value="{{ $account->id }}">
            <label class="field-label">Assign branch</label>
            <select class="field" onchange="this.form.action = this.value">
                @foreach ($branches as $branch)
                    <option value="{{ route('branches.assign', $branch) }}">{{ $branch->name }}</option>
                @endforeach
            </select>
            <button class="btn-ghost mt-3" type="submit">Save branch access</button>
        </form>
        @endif
    @endif
</x-layouts.authenticated>
