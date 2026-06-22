<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Admin panel
            'admin-panel-access',
            // Dashboard
            'dashboard.view',
            // Users
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            // Roles
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            // Permissions
            'permissions.view',
            'permissions.create',
            'permissions.update',
            'permissions.delete',
            // Settings
            'settings.view',
            'settings.update',
            // Languages
            'languages.view',
            'languages.update',
            'languages.sync',
            // Activity Logs
            'activity-logs.view',
            'activity-logs.export',
            'activity-logs.delete',
            // Short URLs
            'short-urls.view',
            // Call logs
            'call-logs.view',
            'nav.crm.show',
            'nav.crm.call-logs.show',
            // Contacts (MyCrmSync)
            'contacts.view',
            'contacts.create',
            'contacts.update',
            'contacts.delete',
            'nav.contact-management.show',
            'nav.contact-management.contacts.show',
            // Tenants (platform companies)
            'tenants.view',
            'tenants.update',
            // AI Settings (removed)
            // Navigation visibility toggles
            'nav.api-management.show',
            'nav.api-management.api-endpoint-mapper.show',
            'nav.api-management.mapped-apis.show',
            'nav.user-management.show',
            'nav.user-management.users.show',
            'nav.user-management.assignment-rules.show',
            'nav.user-management.roles.show',
            'nav.user-management.activity-logs.show',
            'nav.url-management.show',
            'nav.user-management.permissions.show',
            'nav.user-management.tenants.show',
            'nav.settings.show',
            'nav.settings.languages.show',
            //'nav.settings.ai-settings.show',
            //'nav.settings.pricing.show',
            'nav.settings.pool_allocation.show',
            'nav.settings.data-configuration.show',
            'nav.settings.integrations.show',
            'nav.settings.system-settings.show',
            'nav.platform.show',
            'nav.platform.tenants.show',
            // Configurations / Email ingestion removed
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }
}
