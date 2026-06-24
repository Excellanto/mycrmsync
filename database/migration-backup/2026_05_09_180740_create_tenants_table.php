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
        Schema::create('tenants', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('company_name');
            $table->string('account_type', 50)->default('Business');
            $table->string('email')->unique();
            $table->string('pan_card', 20)->nullable();
            $table->string('gst_number', 20)->nullable();
            $table->string('company_logo_path', 512)->nullable();
            $table->string('status')->default('active');
            $table->boolean('email_ingestion_enabled')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
