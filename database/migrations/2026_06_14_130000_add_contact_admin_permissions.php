<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'contacts.view',
            'contacts.create',
            'contacts.update',
            'contacts.delete',
            'nav.contact-management.show',
            'nav.contact-management.contacts.show',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }

    public function down(): void
    {
        Permission::query()
            ->whereIn('name', [
                'contacts.view',
                'contacts.create',
                'contacts.update',
                'contacts.delete',
                'nav.contact-management.show',
                'nav.contact-management.contacts.show',
            ])
            ->delete();
    }
};
