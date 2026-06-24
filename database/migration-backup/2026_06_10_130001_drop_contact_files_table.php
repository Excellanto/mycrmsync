<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('contact_files');
    }

    public function down(): void
    {
        // contact_files was superseded by voice_notes; no rollback data.
    }
};
