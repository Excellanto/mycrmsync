<?php

namespace App\Services\Email;

final class EmailTemplateRenderer
{
    /**
     * @param  array<string, string>  $variables
     */
    public static function render(string $subject, string $htmlBody, array $variables): array
    {
        $replacements = [];
        foreach ($variables as $key => $value) {
            $replacements['{{'.$key.'}}'] = (string) $value;
        }

        return [
            'subject' => strtr($subject, $replacements),
            'html' => strtr($htmlBody, $replacements),
        ];
    }
}
