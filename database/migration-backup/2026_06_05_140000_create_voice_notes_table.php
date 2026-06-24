<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('voice_notes')) {
            return;
        }

        Schema::create('voice_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('contact_id');
            $table->text('location_id')->nullable();
            $table->text('file_name');
            $table->text('storage_path');
            $table->text('recording_url');
            $table->text('recording_url_long');
            $table->string('short_code', 16)->nullable()->index();
            $table->string('transcription_backend', 32);
            $table->text('transcription')->nullable();
            $table->text('summary')->nullable();
            $table->text('note_body')->nullable();
            $table->integer('duration_sec')->nullable();
            $table->text('crm_note_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'contact_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_notes');
    }
};
