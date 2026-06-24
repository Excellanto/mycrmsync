<?php

use App\Models\Integration;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Integration::query()->firstOrCreate(
            ['slug' => 'mycrmsync'],
            [
                'name' => 'MyCrmSync',
                'type' => 'CRM',
                'documentation' => '',
                'fields' => [],
                'enabled' => true,
                'is_system' => true,
            ]
        );
    }

    public function down(): void
    {
        Integration::query()->where('slug', 'mycrmsync')->delete();
    }
};
