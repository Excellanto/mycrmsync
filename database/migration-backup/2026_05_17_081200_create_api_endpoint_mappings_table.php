<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_endpoint_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->nullable()->constrained()->nullOnDelete();
            $table->string('integration_slug', 100);
            $table->string('system_endpoint_id', 64);
            $table->string('system_method', 12);
            $table->string('system_uri');
            $table->string('system_name')->nullable();
            $table->string('crm_endpoint_key', 150);
            $table->string('crm_method', 12);
            $table->string('crm_uri');
            $table->string('crm_name')->nullable();
            $table->json('field_mappings')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(
                ['integration_slug', 'system_method', 'system_uri', 'crm_endpoint_key'],
                'api_endpoint_mappings_unique_endpoint_pair'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_endpoint_mappings');
    }
};
