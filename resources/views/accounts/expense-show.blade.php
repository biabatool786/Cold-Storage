<x-layouts.authenticated title="Expense #{{ $expense->id }}">
    @php $user = auth()->user(); @endphp
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">{{ $expense->description }}</h2>
            <p class="mt-1 text-sm text-muted">{{ number_format((float) $expense->amount, 2) }} · {{ $expense->status }} · {{ $expense->kind }}</p>
        </div>
        <a class="btn-ghost" href="{{ route('expenses.index') }}">Back</a>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @if ($expense->isPending() && $desk->canApproveExpense($user))
        <div class="mb-4 flex flex-wrap gap-2">
            <form method="POST" action="{{ route('expenses.decide', $expense) }}">@csrf<input type="hidden" name="approve" value="1"><button class="btn-primary" type="submit">Approve</button></form>
            <form method="POST" action="{{ route('expenses.decide', $expense) }}" class="flex gap-2">@csrf<input type="hidden" name="approve" value="0"><input name="reason" required placeholder="Reject reason" class="field"><button class="btn-ghost" type="submit">Reject</button></form>
        </div>
    @endif
    @if ($expense->status === 'approved' && $desk->canPostExpense($user))
        <form method="POST" action="{{ route('expenses.post', $expense) }}" class="mb-4">@csrf<button class="btn-primary" type="submit">Post expense</button></form>
    @endif
    @if ($desk->canAttach($user) && in_array($expense->status, ['pending', 'approved'], true))
        <form method="POST" action="{{ route('expenses.attach', $expense) }}" enctype="multipart/form-data" class="mb-4 flex gap-2 rounded-2xl border border-line bg-panel p-5">
            @csrf
            <input type="file" name="receipt" required accept=".pdf,.jpg,.jpeg,.png,.webp" class="field">
            <button class="btn-ghost" type="submit">Attach receipt</button>
        </form>
    @endif
    <ul class="mb-4 space-y-2 text-sm">
        @forelse ($expense->attachments as $att)
            <li><a class="underline" href="{{ route('expenses.attachments.show', $att) }}">{{ $att->original_name }}</a></li>
        @empty
            <li class="text-muted">No attachments.</li>
        @endforelse
    </ul>
    @if ($user->isSuperAdmin() && in_array($expense->status, ['pending', 'approved', 'posted'], true))
        <form method="POST" action="{{ route('expenses.cancel', $expense) }}" class="flex gap-2">
            @csrf
            <input name="reason" required placeholder="Cancel reason" class="field">
            <button class="btn-ghost" type="submit">Cancel expense</button>
        </form>
    @endif
</x-layouts.authenticated>
