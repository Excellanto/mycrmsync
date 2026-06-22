<?php

use App\Models\SiteSetting;
use App\Models\TenantSetting;

/**
 * Symfony's HtmlErrorRenderer calls highlight_file() when rendering exception pages.
 * Some PHP builds disable it via disable_functions — provide a safe fallback.
 */
if (! function_exists('highlight_file')) {
    function highlight_file(string $filename, bool $return = false): string|bool
    {
        if (! is_file($filename) || ! is_readable($filename)) {
            return $return ? '' : false;
        }
        $contents = @file_get_contents($filename);
        if ($contents === false) {
            return $return ? '' : false;
        }
        $escaped = htmlspecialchars($contents, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<pre><code class="language-php">'.$escaped.'</code></pre>';
        if ($return) {
            return $html;
        }
        echo $html;

        return true;
    }
}

if (! function_exists('settings')) {
    function settings(string $key, mixed $default = null): mixed
    {
        $cache = cache()->remember('app_settings', 3600, function () {
            return SiteSetting::query()->get()->keyBy('key');
        });
        $item = $cache[$key] ?? null;
        if (! $item) {
            return $default;
        }
        if ($item->type === 'boolean') {
            return $item->value === '1';
        }
        if ($item->type === 'json') {
            $decoded = json_decode($item->value, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
        }

        return $item->value;
    }
}

if (! function_exists('tenant_settings')) {
    function tenant_settings(int $tenantId, string $key, mixed $default = null): mixed
    {
        return TenantSetting::getValue($tenantId, $key, $default);
    }
}
