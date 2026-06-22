<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        $view = Permission::firstOrCreate([
            'name' => 'short-urls.view',
            'guard_name' => 'web',
        ]);

        $nav = Permission::firstOrCreate([
            'name' => 'nav.user-management.url-management.show',
            'guard_name' => 'web',
        ]);

        Role::query()
            ->where(function ($query) {
                $query->where('is_platform_scope', true)
                    ->orWhere('slug', 'super_admin');
            })
            ->each(function (Role $role) use ($view, $nav): void {
                if (! $role->hasPermissionTo($view)) {
                    $role->givePermissionTo($view);
                }
                if (! $role->hasPermissionTo($nav)) {
                    $role->givePermissionTo($nav);
                }
            });
    }

    public function down(): void
    {
        Permission::query()
            ->whereIn('name', ['short-urls.view', 'nav.user-management.url-management.show'])
            ->where('guard_name', 'web')
            ->delete();
    }
};
