<?php

namespace App\Http\Controllers;

use App\Enums\BranchStatus;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\BranchAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Branch::class);

        $actor = $request->user();

        return view('branches.index', [
            'branches' => $this->visibleBranches($actor)->orderBy('name')->get(),
            'canManage' => $actor->can('create', Branch::class),
            'tenants' => $actor->isSuperAdmin() ? Tenant::query()->orderBy('name')->get() : collect(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('create', Branch::class);
        abort_if($request->user()?->isSuperAdmin(), 403);

        $branch = $this->makeBranch($request, $audit, null);

        return redirect()->route('branches.show', $branch)->with('status', 'Branch created.');
    }

    private function makeBranch(Request $request, AuditLogger $audit, ?int $tenantId): Branch
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $branch = Branch::query()->create([
            ...$data,
            'tenant_id' => $tenantId,
            'status' => BranchStatus::Active,
        ]);

        $audit->record('branch.created', $branch, ['code' => $branch->code], $branch->tenant_id, [
            'module' => 'branches',
            'branch_id' => $branch->id,
            'new' => $data,
        ]);

        $actor = $request->user();
        if ($actor instanceof User && $actor->hasRole(\App\Enums\RoleName::Manager)) {
            $actor->branches()->syncWithoutDetaching([$branch->id]);
        }

        return $branch;
    }

    public function storeForTenant(Request $request, Tenant $tenant, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $this->authorize('create', Branch::class);

        $branch = $this->makeBranch($request, $audit, $tenant->id);

        return redirect()->route('branches.show', $branch)->with('status', 'Branch created.');
    }

    public function show(Request $request, Branch $branch): View
    {
        $this->authorize('view', $branch);

        $actor = $request->user();

        return view('branches.show', [
            'branch' => $branch->load('users'),
            'canAssign' => $actor->can('assign', $branch),
            'candidates' => $actor->can('assign', $branch)
                ? User::query()->where('tenant_id', $branch->tenant_id)->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function update(Request $request, Branch $branch, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $branch);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', Rule::enum(BranchStatus::class)],
        ]);

        $old = $branch->only(['name', 'address', 'phone', 'email', 'status']);
        $branch->update($data);

        $audit->record('branch.updated', $branch, [], $branch->tenant_id, [
            'module' => 'branches',
            'branch_id' => $branch->id,
            'old' => $old,
            'new' => $data,
        ]);

        return back()->with('status', 'Branch updated.');
    }

    public function assign(Request $request, Branch $branch, BranchAccess $access, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('assign', $branch);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $member = User::query()->findOrFail($data['user_id']);

        abort_unless($access->allows($request->user(), $branch), 403);
        abort_unless((int) $member->tenant_id === (int) $branch->tenant_id, 404);

        $branch->users()->syncWithoutDetaching([$member->id]);

        $audit->record('branch.user_assigned', $branch, ['user_id' => $member->id], $branch->tenant_id, [
            'module' => 'branches',
            'branch_id' => $branch->id,
            'new' => ['user_id' => $member->id],
        ]);

        return back()->with('status', 'Branch access saved.');
    }

    private function visibleBranches(User $actor)
    {
        $query = Branch::query();

        if ($actor->isSuperAdmin() || $actor->hasRole(\App\Enums\RoleName::Admin)) {
            return $query;
        }

        return $query->whereIn('id', $actor->branches()->pluck('branches.id'));
    }
}
