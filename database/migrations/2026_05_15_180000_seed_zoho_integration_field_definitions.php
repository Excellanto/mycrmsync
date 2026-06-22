<?php

use App\Models\Integration;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $zoho = Integration::query()->where('slug', 'zoho')->first();

        if ($zoho === null) {
            return;
        }

        $current = $zoho->fields ?? [];
        if (is_array($current) && $current !== []) {
            return;
        }

        $zoho->update([
            'fields' => [
                'OAuth Access Token',
                'OAuth Refresh Token (optional)',
                'CRM API Base URL (optional)',
            ],
        ]);
    }

    public function down(): void
    {
        $zoho = Integration::query()->where('slug', 'zoho')->first();

        if ($zoho === null) {
            return;
        }

        $expected = [
            'OAuth Access Token',
            'OAuth Refresh Token (optional)',
            'CRM API Base URL (optional)',
        ];

        if ($zoho->fields === $expected || json_encode($zoho->fields) === json_encode($expected)) {
            $zoho->update(['fields' => null]);
        }
    }
};
