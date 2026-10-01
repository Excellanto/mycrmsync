<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $names = [
            'tasks.view',
            'tasks.create',
            'tasks.update',
            'nav.crm.tasks.show',
        ];

        foreach ($names as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $names)
            ->get();

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('slug', ['super_admin', 'tenant_admin', 'tenant_user'])
            ->get()
            ->each(function (Role $role) use ($permissions): void {
                $role->givePermissionTo($permissions);
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'tasks.view',
                'tasks.create',
                'tasks.update',
                'nav.crm.tasks.show',
            ])
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
