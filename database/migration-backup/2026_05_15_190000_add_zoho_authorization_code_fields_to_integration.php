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

        $fields = is_array($zoho->fields) ? $zoho->fields : [];
        $toAdd = [
            'OAuth Authorization Code (optional)',
            'OAuth Redirect URI (optional)',
        ];

        foreach ($toAdd as $label) {
            if (! in_array($label, $fields, true)) {
                $fields[] = $label;
            }
        }

        $zoho->update(['fields' => $fields]);
    }

    public function down(): void
    {
        $zoho = Integration::query()->where('slug', 'zoho')->first();

        if ($zoho === null || ! is_array($zoho->fields)) {
            return;
        }

        $strip = [
            'OAuth Authorization Code (optional)',
            'OAuth Redirect URI (optional)',
        ];

        $zoho->update([
            'fields' => array_values(array_filter(
                $zoho->fields,
                static fn ($f) => ! in_array($f, $strip, true)
            )),
        ]);
    }
};
