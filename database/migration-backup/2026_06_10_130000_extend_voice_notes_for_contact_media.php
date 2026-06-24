<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voice_notes', function (Blueprint $table) {
            if (! Schema::hasColumn('voice_notes', 'filetype')) {
                $table->string('filetype', 32)->default('audio')->after('storage_path');
            }

            if (! Schema::hasColumn('voice_notes', 'mime_type')) {
                $table->string('mime_type', 128)->nullable()->after('filetype');
            }

            if (! Schema::hasColumn('voice_notes', 'batch_id')) {
                $table->uuid('batch_id')->nullable()->index()->after('contact_id');
            }
        });

        if (Schema::hasColumn('voice_notes', 'transcription_backend')) {
            DB::statement('ALTER TABLE voice_notes ALTER COLUMN transcription_backend DROP NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('voice_notes', function (Blueprint $table) {
            if (Schema::hasColumn('voice_notes', 'batch_id')) {
                $table->dropIndex(['batch_id']);
                $table->dropColumn('batch_id');
            }

            if (Schema::hasColumn('voice_notes', 'mime_type')) {
                $table->dropColumn('mime_type');
            }

            if (Schema::hasColumn('voice_notes', 'filetype')) {
                $table->dropColumn('filetype');
            }
        });
    }
};
