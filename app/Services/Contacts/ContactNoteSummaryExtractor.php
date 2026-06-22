<?php

namespace App\Services\Contacts;

final class ContactNoteSummaryExtractor
{
    public static function fromBody(string $body): string
    {
        $body = trim(html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($body === '') {
            return '';
        }

        if (preg_match('/^Summary:\s*(.+?)(?:\n\n|\z)/ms', $body, $matches) === 1) {
            return trim($matches[1]);
        }

        if (preg_match('/\n\nSummary:\s*(.+?)(?:\n\n|\z)/ms', $body, $matches) === 1) {
            return trim($matches[1]);
        }

        return '';
    }
}
