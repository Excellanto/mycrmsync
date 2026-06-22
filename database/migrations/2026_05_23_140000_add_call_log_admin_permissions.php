<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $guard = 'web';

        foreach ([
            'call-logs.view',
            'nav.crm.show',
            'nav.crm.call-logs.show',
        ] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::query()
            ->whereIn('name', [
                'call-logs.view',
                'nav.crm.show',
                'nav.crm.call-logs.show',
            ])
            ->where('guard_name', 'web')
            ->delete();
    }
};
