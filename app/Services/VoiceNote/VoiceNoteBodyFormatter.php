<?php

namespace App\Services\VoiceNote;

final class VoiceNoteBodyFormatter
{
    public function format(
        string $summary,
        string $transcription,
        ?string $existingNoteText = null,
    ): string {
        $sections = [];

        $existing = trim((string) $existingNoteText);
        if ($existing !== '') {
            $sections[] = $existing;
        }

        $summary = trim($summary);
        if ($summary !== '') {
            $sections[] = 'Summary: '.$summary;
        }

        $transcription = trim($transcription);
        if ($transcription !== '') {
            $sections[] = 'Transcription: '.$transcription;
        }

        return implode("\n\n", $sections);
    }
}
