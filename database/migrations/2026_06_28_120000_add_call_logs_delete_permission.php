<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'call-logs.delete', 'guard_name' => 'web']);
    }

    public function down(): void
    {
        Permission::query()
            ->where('name', 'call-logs.delete')
            ->where('guard_name', 'web')
            ->delete();
    }
};
