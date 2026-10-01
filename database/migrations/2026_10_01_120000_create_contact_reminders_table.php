<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_reminders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('contact_id');
            $table->string('contact_note_id')->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('remind_at');
            $table->string('status', 20)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->string('contact_name')->default('');
            $table->text('note_excerpt')->default('');
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'status', 'remind_at']);
            $table->index(['tenant_id', 'contact_id']);
            $table->index('contact_note_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_reminders');
    }
};
