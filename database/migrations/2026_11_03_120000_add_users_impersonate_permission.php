<?php

use App\Models\Role;
use App\Support\ApplicationCache;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate([
            'name' => 'users.impersonate',
            'guard_name' => 'web',
        ]);

        Role::query()
            ->where('guard_name', 'web')
            ->where(function ($query) {
                $query->where('is_platform_scope', true)
                    ->orWhere('slug', 'super_admin');
            })
            ->get()
            ->each(function (Role $role) use ($permission): void {
                if (! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        ApplicationCache::bumpUserAuthVersion();
    }

    public function down(): void
    {
        Permission::query()
            ->where('guard_name', 'web')
            ->where('name', 'users.impersonate')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        ApplicationCache::bumpUserAuthVersion();
    }
};
