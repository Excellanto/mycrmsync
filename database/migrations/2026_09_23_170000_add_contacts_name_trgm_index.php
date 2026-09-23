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
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('contacts')) {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('DROP INDEX IF EXISTS contacts_name_trgm_idx');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION contacts_name_norm(first_name text, last_name text)
            RETURNS text
            LANGUAGE sql
            IMMUTABLE
            PARALLEL SAFE
            AS $$
                SELECT lower(btrim(coalesce(first_name, '') || ' ' || coalesce(last_name, '')))
            $$
        SQL);

        DB::statement(
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS contacts_name_trgm_idx ON contacts USING gin (contacts_name_norm(first_name, last_name) gin_trgm_ops)'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS contacts_name_trgm_idx');
        DB::statement('DROP FUNCTION IF EXISTS contacts_name_norm(text, text)');
    }
};
