<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'web';

        $superAdmin = Role::updateOrCreate(
            ['name' => 'Super Admin', 'guard_name' => $guard],
            ['slug' => 'super_admin', 'is_platform_scope' => true]
        );
        $tenantAdmin = Role::updateOrCreate(
            ['name' => 'Tenant Admin', 'guard_name' => $guard],
            ['slug' => 'tenant_admin', 'is_platform_scope' => false]
        );
        $tenantUser = Role::updateOrCreate(
            ['name' => 'Tenant User', 'guard_name' => $guard],
            ['slug' => 'tenant_user', 'is_platform_scope' => false]
        );

        $allPermissions = Permission::where('guard_name', $guard)->get();
        // Platform tenant list (all companies / admin nav) — only Super Admin by default; others get it only via Roles matrix.
        $excludedForTenantScoped = [
            'nav.user-management.tenants.show',
            'tenants.view',
            'tenants.update',
        ];
        $tenantScopedPermissions = $allPermissions->reject(
            fn ($p) => in_array($p->name, $excludedForTenantScoped, true)
        )->values();

        // Super Admin: always every permission in the system (platform-wide).
        $superAdmin->syncPermissions(Permission::where('guard_name', $guard)->get());

        $tenantAdmin->syncPermissions($tenantScopedPermissions);
        $tenantUser->syncPermissions($tenantScopedPermissions);
    }
}
