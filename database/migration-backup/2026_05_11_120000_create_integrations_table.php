<?php

use Database\Seeders\IntegrationSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 10);
            $table->string('documentation')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique('name');
        });

        if (! Schema::hasTable('site_settings')) {
            return;
        }

        $setting = DB::table('site_settings')->where('key', 'integrations.crm_connections')->first();

        if (! $setting || blank($setting->value)) {
            return;
        }

        $decoded = json_decode($setting->value, true);

        if (! is_array($decoded)) {
            return;
        }

        foreach ($decoded as $item) {
            $name = trim((string) ($item['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $canonical = str($name)->squish()->title()->toString();

            $exists = DB::table('integrations')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($canonical)])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('integrations')->insert([
                'name' => $canonical,
                'type' => 'CRM',
                'documentation' => null,
                'enabled' => filter_var($item['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'is_system' => ! filter_var($item['custom'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('site_settings')->where('key', 'integrations.crm_connections')->delete();

        (new IntegrationSeeder())->run();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
