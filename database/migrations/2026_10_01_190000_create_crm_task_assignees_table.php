<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_task_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('crm_task_id')->constrained('crm_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['crm_task_id', 'user_id']);
        });

        // Keep existing tasks visible only to the user who already owned them.
        $rows = DB::table('crm_tasks')->select('id', 'user_id')->get();
        foreach ($rows as $row) {
            DB::table('crm_task_assignees')->insert([
                'crm_task_id' => $row->id,
                'user_id' => $row->user_id,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_task_assignees');
    }
};
