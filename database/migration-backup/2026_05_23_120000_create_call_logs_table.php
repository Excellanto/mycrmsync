<?php

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
        if (Schema::hasTable('call_logs')) {
            return;
        }

        Schema::create('call_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('location_id')->nullable()->index();
            $table->text('user_id')->index();
            $table->text('direction');
            $table->text('phone_raw')->nullable();
            $table->text('phone_e164')->nullable();
            $table->text('contact_id')->nullable();
            $table->text('contact_name')->nullable();
            $table->integer('duration_sec')->nullable();
            $table->timestampTz('started_at')->nullable()->index();
            $table->timestampTz('ended_at')->nullable();
            $table->text('sim_account_id')->nullable();
            $table->text('status')->default('synced_device');
            $table->text('sync_fingerprint')->nullable();
            $table->timestampTz('created_at')->useCurrent()->index();
        });

        DB::statement(
            'CREATE UNIQUE INDEX call_logs_dedupe_idx ON call_logs (location_id, COALESCE(user_id, \'\'), sync_fingerprint) WHERE sync_fingerprint IS NOT NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS call_logs_dedupe_idx');

        Schema::dropIfExists('call_logs');
    }
};
