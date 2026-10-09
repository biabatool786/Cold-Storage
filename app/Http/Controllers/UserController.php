<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Http\Requests\Auth\StoreUserRequest;
use App\Http\Requests\Auth\UpdateUserRoleRequest;
use App\Models\Branch;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\AssignUserRole;
use App\Services\Security\AccountSecurity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user();

        $search = trim($request->string('q')->toString());

        return view('users.index', [
            'users' => User::query()
                ->with(['roles', 'tenant'])
                ->when($actor instanceof User && ! $actor->isSuperAdmin() && ! $actor->hasRole(RoleName::Admin), function ($query) use ($actor): void {
                    $branchIds = $actor->branches()->pluck('branches.id');
                    $query->whereHas('branches', fn ($inner) => $inner->whereIn('branches.id', $branchIds));
                })
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($inner) use ($search): void {
                        $inner->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    });
                })
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
            'canManage' => $actor instanceof User && $actor->hasPermission(PermissionName::UsersManage),
            'search' => $search,
        ]);
    }

    public function store(StoreUserRequest $request, AssignUserRole $assignUserRole, AuditLogger $audit): RedirectResponse
    {
        $user = $assignUserRole->createUser(
            $request->user(),
            $request->validated('name'),
            $request->validated('email'),
            $request->validated('password'),
            RoleName::from($request->validated('role')),
        );

        $audit->record('user.created', $user, ['email' => $user->email], $user->tenant_id);

        return redirect()->route('users.index')->with('status', 'User created.');
    }

    public function updateRole(UpdateUserRoleRequest $request, User $user, AssignUserRole $assignUserRole, AuditLogger $audit): RedirectResponse
    {
        $assignUserRole->assign(
            $request->user(),
            $user,
            RoleName::from($request->validated('role')),
        );

        $audit->record('user.role_updated', $user, ['role' => $request->validated('role')], $user->tenant_id);

        return redirect()->route('users.index')->with('status', 'Role updated.');
    }

    public function show(User $user): View
    {
        $this->authorize('view', $user);
        $actor = request()->user();
        if (! $actor->isSuperAdmin() && ! $actor->hasRole(RoleName::Admin) && ! $actor->is($user)) {
            abort_unless(
                $user->branches()->whereIn('branches.id', $actor->branches()->pluck('branches.id'))->exists(),
                403,
            );
        }
        $user->load('roles', 'branches', 'tenant');

        return view('users.show', [
            'account' => $user,
            'canEdit' => $actor->hasPermission('users.edit') && ($actor->isSuperAdmin() || ! $user->isSuperAdmin()),
            'canExecute' => $actor->hasPermission('users.execute') && ($actor->isSuperAdmin() || ! $user->isSuperAdmin()),
            'branches' => $actor->isSuperAdmin()
                ? Branch::query()->when($user->tenant_id, fn ($query) => $query->where('tenant_id', $user->tenant_id))->orderBy('name')->get()
                : Branch::query()->where('tenant_id', $actor->tenant_id)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user, AccountSecurity $security): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);
        $security->updateProfile($request->user(), $user, $data);

        return back()->with('status', 'Profile saved.');
    }

    public function status(Request $request, User $user, AccountSecurity $security): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:ACTIVE,INACTIVE']]);
        $security->setStatus($request->user(), $user, UserStatus::from($data['status']));

        return back()->with('status', 'Account status saved.');
    }

    public function password(Request $request, User $user, AccountSecurity $security): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'confirmed', Password::defaults()]]);
        $security->resetPassword($request->user(), $user, $data['password']);

        return back()->with('status', 'Password reset. The user must sign in again.');
    }

    public function unlock(Request $request, User $user, AccountSecurity $security): RedirectResponse
    {
        $security->unlock($request->user(), $user);

        return back()->with('status', 'Account unlocked.');
    }

    public function forceLogout(Request $request, User $user, AccountSecurity $security): RedirectResponse
    {
        $security->forceLogout($request->user(), $user);

        return back()->with('status', 'Sessions ended.');
    }
}
