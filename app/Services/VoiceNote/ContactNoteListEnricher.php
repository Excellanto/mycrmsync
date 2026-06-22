<?php

namespace App\Services\VoiceNote;

use App\Models\Tenant;
use App\Models\VoiceNote;
use Illuminate\Support\Collection;

final class ContactNoteListEnricher
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function enrich(Tenant $tenant, array $payload): array
    {
        $notes = $payload['notes'] ?? [];
        if (! is_array($notes) || $notes === []) {
            return $payload;
        }

        $noteIds = collect($notes)
            ->map(fn (mixed $note): string => is_array($note) ? trim((string) ($note['id'] ?? '')) : '')
            ->filter()
            ->values()
            ->all();

        $voiceNotesByCrmNoteId = $noteIds === []
            ? collect()
            : VoiceNote::query()
                ->where('tenant_id', $tenant->id)
                ->whereIn('crm_note_id', $noteIds)
                ->orderBy('created_at')
                ->get()
                ->groupBy('crm_note_id');

        $payload['notes'] = array_map(
            fn (mixed $note): mixed => is_array($note)
                ? $this->enrichNote($note, $voiceNotesByCrmNoteId)
                : $note,
            $notes
        );

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $note
     * @param  Collection<string, Collection<int, VoiceNote>>  $voiceNotesByCrmNoteId
     * @return array<string, mixed>
     */
    private function enrichNote(array $note, Collection $voiceNotesByCrmNoteId): array
    {
        $noteId = trim((string) ($note['id'] ?? ''));
        $voiceNotes = $voiceNotesByCrmNoteId->get($noteId, collect());

        if ($voiceNotes->isNotEmpty()) {
            $note['attachments'] = $voiceNotes
                ->map(fn (VoiceNote $row): array => $this->serializeAttachment($row))
                ->values()
                ->all();
        } else {
            $note['attachments'] = $this->structureLegacyAttachments($note['attachments'] ?? []);
        }

        $note['body'] = $this->stripLegacyAttachmentLines((string) ($note['body'] ?? ''));

        return $note;
    }

    /**
     * @return array{filetype: string, fileshorturl: string, fileslongurl: string}
     */
    private function serializeAttachment(VoiceNote $row): array
    {
        return [
            'filetype' => (string) $row->filetype,
            'fileshorturl' => (string) $row->recording_url,
            'fileslongurl' => (string) $row->recording_url_long,
        ];
    }

    /**
     * @param  mixed  $attachments
     * @return list<array{filetype: string, fileshorturl: string, fileslongurl: string}>
     */
    private function structureLegacyAttachments(mixed $attachments): array
    {
        if (! is_array($attachments)) {
            return [];
        }

        $structured = [];

        foreach ($attachments as $attachment) {
            if (is_array($attachment)) {
                $shortUrl = trim((string) (
                    $attachment['fileshorturl']
                        ?? $attachment['url']
                        ?? $attachment['fileshortUrl']
                        ?? ''
                ));
                $longUrl = trim((string) (
                    $attachment['fileslongurl']
                        ?? $attachment['filelongurl']
                        ?? $attachment['recording_url_long']
                        ?? $shortUrl
                ));
                $filetype = trim((string) ($attachment['filetype'] ?? ''));

                if ($shortUrl === '') {
                    continue;
                }

                $structured[] = [
                    'filetype' => $filetype !== '' ? $filetype : $this->filetypeFromUrl($shortUrl),
                    'fileshorturl' => $shortUrl,
                    'fileslongurl' => $longUrl !== '' ? $longUrl : $shortUrl,
                ];

                continue;
            }

            $url = trim((string) $attachment);
            if ($url === '') {
                continue;
            }

            $structured[] = [
                'filetype' => $this->filetypeFromUrl($url),
                'fileshorturl' => $url,
                'fileslongurl' => $url,
            ];
        }

        return $structured;
    }

    private function filetypeFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $extension = strtolower(ltrim((string) pathinfo(is_string($path) ? $path : '', PATHINFO_EXTENSION), '.'));

        return $extension !== '' ? $extension : 'bin';
    }

    private function stripLegacyAttachmentLines(string $body): string
    {
        $clean = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $clean = preg_replace('/^\s*Attachment:\s*.+$/mi', '', $clean) ?? $clean;
        $clean = preg_replace('/^\s*(attachments?|files?)\s*:?\s*$/mi', '', $clean) ?? $clean;
        $clean = preg_replace("/[ \t]+\r?\n/", "\n", $clean) ?? $clean;
        $clean = preg_replace("/\r\n|\r/", "\n", $clean) ?? $clean;
        $clean = preg_replace("/\n{3,}/", "\n\n", $clean) ?? $clean;

        return trim($clean);
    }
}
