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
        // Platform-wide tenant directory stays Super Admin only; Tenant Admin still gets
        // company (own-tenant) management so Integration / Company screens work.
        $excludedForTenantUser = [
            'nav.user-management.tenants.show',
            'tenants.view',
            'tenants.update',
        ];
        $tenantUserPermissions = $allPermissions->reject(
            fn ($p) => in_array($p->name, $excludedForTenantUser, true)
        )->values();

        // Super Admin: always every permission in the system (platform-wide).
        $superAdmin->syncPermissions(Permission::where('guard_name', $guard)->get());

        // Tenant Admin: all non-platform-directory permissions including own-company tenants.*.
        $tenantAdmin->syncPermissions($allPermissions);
        $tenantUser->syncPermissions($tenantUserPermissions);
    }
}
