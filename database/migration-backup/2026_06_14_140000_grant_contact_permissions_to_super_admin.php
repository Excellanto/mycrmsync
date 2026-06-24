<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $guard = 'web';

        $names = [
            'contacts.view',
            'contacts.create',
            'contacts.update',
            'contacts.delete',
            'nav.contact-management.show',
            'nav.contact-management.contacts.show',
        ];

        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
        }

        $superAdmin = Role::query()
            ->where('guard_name', $guard)
            ->where('slug', 'super_admin')
            ->first();

        if ($superAdmin !== null) {
            $superAdmin->givePermissionTo(
                Permission::query()
                    ->where('guard_name', $guard)
                    ->whereIn('name', $names)
                    ->get()
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $guard = 'web';

        $superAdmin = Role::query()
            ->where('guard_name', $guard)
            ->where('slug', 'super_admin')
            ->first();

        if ($superAdmin !== null) {
            $superAdmin->revokePermissionTo([
                'contacts.view',
                'contacts.create',
                'contacts.update',
                'contacts.delete',
                'nav.contact-management.show',
                'nav.contact-management.contacts.show',
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
