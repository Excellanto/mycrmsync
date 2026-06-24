<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        $parentNav = Permission::firstOrCreate([
            'name' => 'nav.url-management.show',
            'guard_name' => 'web',
        ]);

        $legacyNav = Permission::query()
            ->where('name', 'nav.user-management.url-management.show')
            ->where('guard_name', 'web')
            ->first();

        if ($legacyNav !== null) {
            Role::query()->each(function (Role $role) use ($parentNav, $legacyNav): void {
                if ($role->hasPermissionTo($legacyNav) && ! $role->hasPermissionTo($parentNav)) {
                    $role->givePermissionTo($parentNav);
                }
            });

            $legacyNav->delete();
        }

        Role::query()
            ->where(function ($query) {
                $query->where('is_platform_scope', true)
                    ->orWhere('slug', 'super_admin');
            })
            ->each(function (Role $role) use ($parentNav): void {
                if (! $role->hasPermissionTo($parentNav)) {
                    $role->givePermissionTo($parentNav);
                }
            });
    }

    public function down(): void
    {
        Permission::query()
            ->where('name', 'nav.url-management.show')
            ->where('guard_name', 'web')
            ->delete();

        Permission::firstOrCreate([
            'name' => 'nav.user-management.url-management.show',
            'guard_name' => 'web',
        ]);
    }
};
