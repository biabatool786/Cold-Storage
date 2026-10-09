<?php

namespace App\Services\Authorization;

use Illuminate\Support\Facades\DB;

class RemapLegacyRoles
{
    /**
     * Viewer has no documented role, so those users become Supervisor.
     */
    public function run(): void
    {
        $now = now();

        foreach (['Admin', 'Supervisor', 'Gatekeeper'] as $name) {
            $existing = DB::table('roles')->where('name', $name)->first();

            if ($existing === null) {
                DB::table('roles')->insert([
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                continue;
            }

            if ($existing->deleted_at !== null) {
                DB::table('roles')->where('id', $existing->id)->update([
                    'deleted_at' => null,
                    'updated_at' => $now,
                ]);
            }
        }

        $map = [
            'Tenant Admin' => 'Admin',
            'Storekeeper' => 'Supervisor',
            'Quality Officer' => 'Supervisor',
            'Monitoring Operator' => 'Supervisor',
            'Viewer' => 'Supervisor',
        ];

        foreach ($map as $legacyName => $productionName) {
            $legacy = DB::table('roles')->where('name', $legacyName)->whereNull('deleted_at')->first();
            $production = DB::table('roles')->where('name', $productionName)->whereNull('deleted_at')->first();

            if ($legacy === null || $production === null) {
                continue;
            }

            $assignments = DB::table('user_roles')->where('role_id', $legacy->id)->get();

            foreach ($assignments as $assignment) {
                $alreadyAssigned = DB::table('user_roles')
                    ->where('user_id', $assignment->user_id)
                    ->where('role_id', $production->id)
                    ->exists();

                if ($alreadyAssigned) {
                    DB::table('user_roles')->where('id', $assignment->id)->delete();

                    continue;
                }

                DB::table('user_roles')->where('id', $assignment->id)->update([
                    'role_id' => $production->id,
                    'updated_at' => $now,
                ]);
            }

            DB::table('roles')->where('id', $legacy->id)->update([
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
