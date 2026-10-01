<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('contact_id')->nullable();
            $table->string('contact_name')->default('');
            $table->string('contact_note_id')->nullable();
            $table->foreignUuid('contact_reminder_id')->nullable()->unique()->constrained('contact_reminders')->cascadeOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestamp('due_at');
            $table->boolean('is_reminder')->default(false);
            $table->string('status', 20)->default('open');
            $table->timestamp('completed_at')->nullable();
            $table->string('external_provider', 32)->nullable();
            $table->string('external_id')->nullable();
            $table->string('sync_error', 500)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'due_at']);
            $table->index(['tenant_id', 'user_id', 'status']);
            $table->index(['tenant_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_tasks');
    }
};
