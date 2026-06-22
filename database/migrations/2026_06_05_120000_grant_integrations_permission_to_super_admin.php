<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::query()
            ->where('name', 'nav.settings.integrations.show')
            ->where('guard_name', 'web')
            ->first();

        if (! $permission) {
            return;
        }

        Role::query()
            ->where(function ($query) {
                $query->where('is_platform_scope', true)
                    ->orWhere('slug', 'super_admin');
            })
            ->each(function (Role $role) use ($permission): void {
                if (! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            });
    }

    public function down(): void
    {
        // Intentionally no-op: do not revoke permissions from Super Admin on rollback.
    }
};
