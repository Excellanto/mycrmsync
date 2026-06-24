<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CONCURRENTLY cannot run inside a transaction.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        if (Schema::hasTable('call_logs')) {
            $this->createIndexConcurrently(
                'call_logs_user_started_id_idx',
                'ON call_logs (user_id, started_at DESC NULLS LAST, id DESC)',
            );
            $this->createIndexConcurrently(
                'call_logs_effective_at_idx',
                'ON call_logs (COALESCE(started_at, created_at))',
            );
            $this->createIndexConcurrently(
                'call_logs_user_effective_at_idx',
                'ON call_logs (user_id, COALESCE(started_at, created_at))',
            );
            $this->createIndexConcurrently(
                'call_logs_contact_id_idx',
                "ON call_logs (contact_id) WHERE contact_id IS NOT NULL AND contact_id <> ''",
            );
            $this->createIndexConcurrently(
                'call_logs_phone_e164_trgm_idx',
                'ON call_logs USING gin (phone_e164 gin_trgm_ops)',
            );
            $this->createIndexConcurrently(
                'call_logs_phone_raw_trgm_idx',
                'ON call_logs USING gin (phone_raw gin_trgm_ops)',
            );
        }

        if (Schema::hasTable('call_recordings')) {
            $this->createIndexConcurrently(
                'call_recordings_tenant_created_at_idx',
                'ON call_recordings (tenant_id, created_at DESC)',
            );
            $this->createIndexConcurrently(
                'call_recordings_created_at_tenant_idx',
                'ON call_recordings (created_at, tenant_id)',
            );
            $this->createIndexConcurrently(
                'call_recordings_tenant_contact_idx',
                "ON call_recordings (tenant_id, contact_id) WHERE contact_id IS NOT NULL AND contact_id <> ''",
            );
        }

        if (Schema::hasTable('contacts')) {
            $this->createIndexConcurrently(
                'contacts_tenant_created_at_idx',
                'ON contacts (tenant_id, created_at DESC)',
            );
            $this->createIndexConcurrently(
                'contacts_tenant_assigned_to_idx',
                'ON contacts (tenant_id, assigned_to) WHERE assigned_to IS NOT NULL',
            );
            $this->createIndexConcurrently(
                'contacts_tenant_updated_at_idx',
                'ON contacts (tenant_id, updated_at DESC)',
            );
            $this->createIndexConcurrently(
                'contacts_tags_gin_idx',
                'ON contacts USING gin ((tags::jsonb) jsonb_path_ops)',
            );
            $this->createIndexConcurrently(
                'contacts_phone_trgm_idx',
                'ON contacts USING gin (phone gin_trgm_ops)',
            );
        }

        if (Schema::hasTable('contact_notes')) {
            $this->createIndexConcurrently(
                'contact_notes_tenant_contact_idx',
                'ON contact_notes (tenant_id, contact_id)',
            );
        }

        if (Schema::hasTable('voice_notes')) {
            $this->createIndexConcurrently(
                'voice_notes_tenant_crm_note_id_idx',
                'ON voice_notes (tenant_id, crm_note_id) WHERE crm_note_id IS NOT NULL',
            );
        }

        if (Schema::hasTable('activity_logs')) {
            $this->createIndexConcurrently(
                'activity_logs_module_created_at_idx',
                'ON activity_logs (module, created_at DESC)',
            );
        }

        if (Schema::hasTable('integrations')) {
            $this->createIndexConcurrently(
                'integrations_enabled_type_name_idx',
                'ON integrations (enabled, type, name) WHERE enabled = true',
            );
        }

        if (Schema::hasTable('tenants')) {
            $this->createIndexConcurrently(
                'tenants_status_idx',
                'ON tenants (status)',
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $indexes = [
            'tenants_status_idx',
            'integrations_enabled_type_name_idx',
            'activity_logs_module_created_at_idx',
            'voice_notes_tenant_crm_note_id_idx',
            'contact_notes_tenant_contact_idx',
            'contacts_phone_trgm_idx',
            'contacts_tags_gin_idx',
            'contacts_tenant_updated_at_idx',
            'contacts_tenant_assigned_to_idx',
            'contacts_tenant_created_at_idx',
            'call_recordings_tenant_contact_idx',
            'call_recordings_created_at_tenant_idx',
            'call_recordings_tenant_created_at_idx',
            'call_logs_phone_raw_trgm_idx',
            'call_logs_phone_e164_trgm_idx',
            'call_logs_contact_id_idx',
            'call_logs_user_effective_at_idx',
            'call_logs_effective_at_idx',
            'call_logs_user_started_id_idx',
        ];

        foreach ($indexes as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }
    }

    private function createIndexConcurrently(string $name, string $definition): void
    {
        DB::statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS {$name} {$definition}");
    }
};
