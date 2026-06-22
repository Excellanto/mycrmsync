<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Integration extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'documentation',
        'fields',
        'enabled',
        'is_system',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'is_system' => 'boolean',
        'fields' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (Integration $integration) {
            if (! $integration->isDirty('name') && filled($integration->slug)) {
                return;
            }

            static::assignUniqueSlug($integration);
        });
    }

    /**
     * URL/key-safe slug from display name (aligned with Str::slug on ASCII names).
     */
    public static function slugFromName(string $name): string
    {
        $slug = Str::slug(Str::squish($name));

        return $slug !== '' ? $slug : 'integration';
    }

    /**
     * Set {@see $integration}'s slug uniquely among other integrations.
     */
    public static function assignUniqueSlug(Integration $integration): void
    {
        $base = static::slugFromName((string) $integration->name);
        $slug = $base;
        $suffix = 2;

        while (
            static::query()
                ->where('slug', $slug)
                ->when(
                    $integration->exists,
                    fn ($query) => $query->where('id', '!=', $integration->id)
                )
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        $integration->slug = $slug;
    }

    /**
     * Enabled integration names shown in integration/CRM dropdowns across the app.
     *
     * @return array<int, string>
     */
    public static function enabledIntegrationNames(): array
    {
        return static::query()
            ->where('enabled', true)
            ->orderBy('type')
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * Whether the label already ends with a "(optional)" suffix (legacy / display).
     */
    public static function fieldLabelHasOptionalSuffix(string $label): bool
    {
        return (bool) preg_match('/\(optional\)\s*$/iu', $label);
    }

    /**
     * Strip a trailing "(optional)" suffix for the human-readable label.
     */
    public static function fieldLabelWithoutOptionalSuffix(string $label): string
    {
        return trim(preg_replace('/\s*\(optional\)\s*$/iu', '', Str::squish($label)));
    }

    /**
     * Slug input for generating {@see fieldSpecsFromEntries()} keys. Keeps keys stable for
     * legacy definitions that used "Label (optional)" instead of a structured optional flag.
     *
     * @return non-empty-string
     */
    public static function fieldKeySlugSource(string $label, bool $optional): string
    {
        $squished = Str::squish($label);

        if (static::fieldLabelHasOptionalSuffix($squished)) {
            return $squished;
        }

        if ($optional && $squished !== '') {
            return $squished.' (optional)';
        }

        return $squished !== '' ? $squished : 'field';
    }

    /**
     * Normalize field definitions: legacy list of label strings or list of
     * {@see array{label: string, optional?: bool}}.
     *
     * @param  mixed  $raw
     * @return array<int, array{label: string, optional: bool}>
     */
    public static function normalizeFieldEntries($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        $seen = [];

        foreach ($raw as $item) {
            if (is_string($item)) {
                $label = Str::squish($item);
                if ($label === '') {
                    continue;
                }
                $optional = static::fieldLabelHasOptionalSuffix($label);
                $display = $optional ? static::fieldLabelWithoutOptionalSuffix($label) : $label;
            } elseif (is_array($item) && isset($item['label'])) {
                $label = Str::squish((string) $item['label']);
                if ($label === '') {
                    continue;
                }
                $optional = filter_var($item['optional'] ?? false, FILTER_VALIDATE_BOOLEAN);
                if (static::fieldLabelHasOptionalSuffix($label)) {
                    $optional = true;
                    $display = static::fieldLabelWithoutOptionalSuffix($label);
                } else {
                    $display = $label;
                }
            } else {
                continue;
            }

            $dedupe = mb_strtolower($display);

            if (isset($seen[$dedupe])) {
                continue;
            }

            $seen[$dedupe] = true;
            $out[] = ['label' => $display, 'optional' => $optional];
        }

        return $out;
    }

    /**
     * Stable keys per configured field (for storing tenant integration values).
     *
     * @return array<int, array{key: string, label: string, optional: bool}>
     */
    public static function fieldSpecsFromEntries(array $entries): array
    {
        $used = [];
        $specs = [];

        foreach ($entries as $entry) {
            $label = $entry['label'] ?? '';
            $optional = (bool) ($entry['optional'] ?? false);
            $slugSource = static::fieldKeySlugSource($label, $optional);

            $base = Str::slug($slugSource, '_');
            if ($base === '') {
                $base = 'field';
            }

            $key = $base;
            $suffix = 2;

            while (isset($used[$key])) {
                $key = $base.'_'.$suffix;
                $suffix++;
            }

            $used[$key] = true;
            $specs[] = ['key' => $key, 'label' => $label, 'optional' => $optional];
        }

        return $specs;
    }

    /**
     * @return array<int, array{key: string, label: string, optional: bool}>
     */
    public function fieldSpecs(): array
    {
        return static::fieldSpecsFromEntries(static::normalizeFieldEntries($this->fields ?? []));
    }
}
