<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contact_files')) {
            return;
        }

        Schema::create('contact_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('contact_id');
            $table->text('file_name');
            $table->text('storage_path');
            $table->string('filetype', 32);
            $table->string('mime_type', 128)->nullable();
            $table->text('file_url');
            $table->text('file_url_long');
            $table->string('short_code', 16)->nullable()->index();
            $table->text('crm_note_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'contact_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_files');
    }
};
