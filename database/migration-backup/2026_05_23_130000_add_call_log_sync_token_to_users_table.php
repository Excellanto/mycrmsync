<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'call_log_sync_token_hash')) {
                $table->string('call_log_sync_token_hash', 64)->nullable()->after('intsysuser');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'call_log_sync_token_hash')) {
                $table->dropColumn('call_log_sync_token_hash');
            }
        });
    }
};
