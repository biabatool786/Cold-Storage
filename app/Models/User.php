<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Concerns\TenantAware;
use App\Models\Contracts\BelongsToTenant;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements BelongsToTenant
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, TenantAware;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'status',
        'phone',
        'locked_at',
        'failed_login_attempts',
        'two_factor_secret',
        'two_factor_confirmed_at',
        'session_invalidated_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'locked_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'session_invalidated_at' => 'datetime',
        ];
    }

    public static function tenantScopeAllowsMissingContext(): bool
    {
        return true;
    }

    public function isSuperAdmin(): bool
    {
        if ($this->tenant_id !== null) {
            return false;
        }

        $this->loadMissing('roles');

        return $this->roles->contains(
            fn (Role $assigned): bool => $assigned->name === RoleName::SuperAdmin->value,
        );
    }

    /**
     * A sign-in is allowed for an active tenant user, or an active platform Super Admin.
     */
    public function isActiveAccount(): bool
    {
        return $this->status === UserStatus::Active
            && ($this->isSuperAdmin() || $this->tenant_id !== null);
    }

    public function hasRole(RoleName|string $role): bool
    {
        $name = $role instanceof RoleName ? $role->value : $role;

        if ($name === RoleName::SuperAdmin->value) {
            return $this->isSuperAdmin();
        }

        $this->loadMissing('roles');

        return $this->roles->contains(fn (Role $assigned): bool => $assigned->name === $name);
    }

    /**
     * @param  list<RoleName|string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function hasPermission(PermissionName|string $permission): bool
    {
        $name = $permission instanceof PermissionName ? $permission->value : $permission;

        $this->loadMissing('roles.permissions');

        return $this->roles->contains(function (Role $role) use ($name): bool {
            if ($role->name === RoleName::SuperAdmin->value && ! $this->isSuperAdmin()) {
                return false;
            }

            return $role->permissions->contains(
                fn (Permission $permission): bool => $permission->name === $name,
            );
        });
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withTimestamps();
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'user_branches')->withTimestamps();
    }
}
