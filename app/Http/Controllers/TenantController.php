<?php

namespace App\Http\Controllers;

use App\Enums\TenantStatus;
use App\Http\Requests\StoreTenantAdminRequest;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Http\Requests\UpdateTenantStatusRequest;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\AssignUserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function index(): View
    {
        abort_unless(request()->user()?->can('viewAny', Tenant::class), 403);

        return view('tenants.index', [
            'tenants' => Tenant::query()->withCount('users')->latest()->paginate(15),
        ]);
    }

    public function create(): View
    {
        abort_unless(request()->user()?->can('create', Tenant::class), 403);

        return view('tenants.form', ['tenant' => new Tenant]);
    }

    public function store(StoreTenantRequest $request, AuditLogger $audit): RedirectResponse
    {
        $tenant = Tenant::query()->create([
            ...$request->safe()->only(['name', 'email', 'phone', 'address', 'city', 'country']),
            'slug' => $this->slug($request->validated('name')),
            'status' => TenantStatus::Active,
        ]);

        $audit->record('tenant.created', $tenant, ['name' => $tenant->name], $tenant->id);

        return redirect()->route('tenants.show', $tenant)->with('status', 'Cold storage created.');
    }

    public function show(Tenant $tenant): View
    {
        abort_unless(request()->user()?->can('view', $tenant), 403);

        return view('tenants.show', [
            'tenant' => $tenant->loadCount('users'),
            'users' => User::query()->with('roles')->where('tenant_id', $tenant->id)->orderBy('name')->get(),
            'subscription' => Subscription::query()->with('plan')->where('tenant_id', $tenant->id)->latest('starts_at')->first(),
        ]);
    }

    public function edit(Tenant $tenant): View
    {
        abort_unless(request()->user()?->can('update', $tenant), 403);

        return view('tenants.form', ['tenant' => $tenant]);
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant, AuditLogger $audit): RedirectResponse
    {
        $tenant->update($request->safe()->only(['name', 'email', 'phone', 'address', 'city', 'country']));
        $audit->record('tenant.updated', $tenant, ['name' => $tenant->name], $tenant->id);

        return redirect()->route('tenants.show', $tenant)->with('status', 'Cold storage updated.');
    }

    public function status(UpdateTenantStatusRequest $request, Tenant $tenant, AuditLogger $audit): RedirectResponse
    {
        $status = TenantStatus::from($request->validated('status'));
        $tenant->update(['status' => $status]);
        $audit->record('tenant.status_changed', $tenant, ['status' => $status->value], $tenant->id);

        return back()->with('status', 'Status updated.');
    }

    public function storeAdmin(StoreTenantAdminRequest $request, Tenant $tenant, AssignUserRole $roles, AuditLogger $audit): RedirectResponse
    {
        $user = $roles->createForTenant(
            $request->user(),
            $tenant,
            $request->validated('name'),
            $request->validated('email'),
            $request->validated('password'),
        );

        $audit->record('tenant_admin.created', $user, ['email' => $user->email], $tenant->id);

        return back()->with('status', 'Admin assigned.');
    }

    private function slug(string $name): string
    {
        $base = Str::slug($name) ?: 'cold-storage';
        $slug = $base;
        $suffix = 2;

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
