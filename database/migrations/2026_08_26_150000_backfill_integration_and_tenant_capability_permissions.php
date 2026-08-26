<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Nav toggles previously unlocked the sidebar without the capability permissions
     * the controllers/policies actually check — backfill those capabilities.
     */
    public function up(): void
    {
        $grants = [
            'nav.settings.integrations.show' => [
                'settings.view',
                'settings.update',
                'nav.settings.show',
            ],
            'nav.settings.data-configuration.show' => [
                'settings.view',
                'settings.update',
                'nav.settings.show',
            ],
            'nav.settings.email-templates.show' => [
                'settings.view',
                'settings.update',
                'nav.settings.show',
            ],
            'nav.settings.system-settings.show' => [
                'settings.view',
                'settings.update',
                'nav.settings.show',
            ],
            'nav.user-management.tenants.show' => [
                'tenants.view',
                'tenants.update',
            ],
        ];

        foreach ($grants as $navPermission => $capabilities) {
            $roles = Role::query()
                ->where('guard_name', 'web')
                ->whereHas('permissions', fn ($q) => $q->where('name', $navPermission))
                ->get();

            foreach ($roles as $role) {
                foreach ($capabilities as $capability) {
                    $permission = Permission::firstOrCreate([
                        'name' => $capability,
                        'guard_name' => 'web',
                    ]);
                    if (! $role->hasPermissionTo($permission)) {
                        $role->givePermissionTo($permission);
                    }
                }
            }
        }

        // Tenant Admin should manage own company (Integration tab) by default.
        $tenantAdmin = Role::query()
            ->where('guard_name', 'web')
            ->where(function ($q) {
                $q->where('slug', 'tenant_admin')->orWhere('name', 'Tenant Admin');
            })
            ->first();

        if ($tenantAdmin) {
            foreach ([
                'tenants.view',
                'tenants.update',
                'nav.user-management.tenants.show',
                'nav.user-management.show',
                'settings.view',
                'settings.update',
                'nav.settings.show',
                'nav.settings.integrations.show',
            ] as $name) {
                $permission = Permission::firstOrCreate([
                    'name' => $name,
                    'guard_name' => 'web',
                ]);
                if (! $tenantAdmin->hasPermissionTo($permission)) {
                    $tenantAdmin->givePermissionTo($permission);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (class_exists(\App\Support\ApplicationCache::class)) {
            \App\Support\ApplicationCache::bumpUserAuthVersion();
        }
    }

    public function down(): void
    {
        // Intentionally no-op: do not revoke permissions that may have been granted intentionally.
    }
};
