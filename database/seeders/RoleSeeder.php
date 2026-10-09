<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Seed the six documented production roles.
     * Legacy roles are remapped by migration and are not recreated here.
     */
    public function run(): void
    {
        foreach (RoleName::cases() as $role) {
            Role::query()->withTrashed()->firstOrCreate([
                'name' => $role->value,
            ]);
        }
    }
}
