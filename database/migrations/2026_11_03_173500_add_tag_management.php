<?php

use App\Models\Role;
use App\Support\ApplicationCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        $names = [
            'tags.view',
            'tags.create',
            'tags.update',
            'tags.delete',
            'nav.contact-management.tags.show',
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
        ApplicationCache::bumpUserAuthVersion();
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_tags');

        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'tags.view',
                'tags.create',
                'tags.update',
                'tags.delete',
                'nav.contact-management.tags.show',
            ])
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        ApplicationCache::bumpUserAuthVersion();
    }
};
