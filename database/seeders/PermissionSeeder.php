<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionCatalog::names() as $name) {
            Permission::query()->withTrashed()->updateOrCreate(
                ['name' => $name],
                ['description' => str_replace('.', ' ', $name)],
            );
        }

        foreach (RoleName::cases() as $roleName) {
            $role = Role::query()->where('name', $roleName->value)->firstOrFail();
            $ids = Permission::query()->whereIn('name', PermissionCatalog::for($roleName))->pluck('id');
            $role->permissions()->sync($ids);
        }
    }
}
