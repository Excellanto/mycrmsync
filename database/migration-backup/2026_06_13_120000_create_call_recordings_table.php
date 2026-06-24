<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('call_recordings')) {
            return;
        }

        Schema::create('call_recordings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('call_log_id')->nullable()->index();
            $table->text('contact_id')->nullable();
            $table->text('file_name')->nullable();
            $table->text('storage_path')->nullable();
            $table->string('filetype', 16)->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->text('recording_url')->nullable();
            $table->text('recording_url_long')->nullable();
            $table->string('short_code', 16)->nullable()->index();
            $table->string('transcription_backend', 32)->nullable();
            $table->text('transcription')->nullable();
            $table->text('summary')->nullable();
            $table->json('sentiment')->nullable();
            $table->integer('duration_sec')->nullable();
            $table->string('status', 32)->default('completed');
            $table->timestamps();

            $table->index(['tenant_id', 'call_log_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_recordings');
    }
};
