<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('applicant_activities');
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('resume_batches');
        Schema::dropIfExists('candidate_tenant');
        Schema::dropIfExists('candidates');
        Schema::dropIfExists('tenant_pipeline_stages');
        Schema::dropIfExists('phone_otps');
    }

    public function down(): void
    {
        // Job portal / recruiting schema removed; restore from backups if needed.
    }
};
