<?php

namespace App\Services\VoiceNote;

final class ContactMediaNoteBodyFormatter
{
    public function __construct(
        private VoiceNoteBodyFormatter $voiceNoteBodyFormatter,
    ) {}

    /**
     * @param  list<array{summary: string, transcription: string}>  $audioSections
     */
    public function formatBatch(
        ?string $existingNoteText,
        array $audioSections,
    ): string {
        $sections = [];

        $existing = trim((string) $existingNoteText);
        if ($existing !== '') {
            $sections[] = $existing;
        }

        foreach ($audioSections as $audioSection) {
            $sectionBody = $this->voiceNoteBodyFormatter->format(
                $audioSection['summary'] ?? '',
                $audioSection['transcription'] ?? '',
            );

            if ($sectionBody !== '') {
                $sections[] = $sectionBody;
            }
        }

        return implode("\n\n", $sections);
    }
}
