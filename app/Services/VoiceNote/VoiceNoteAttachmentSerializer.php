<?php

namespace App\Services\VoiceNote;

use App\Models\VoiceNote;
use Illuminate\Support\Collection;

final class VoiceNoteAttachmentSerializer
{
    /**
     * @param  Collection<int, VoiceNote>|iterable<int, VoiceNote>  $rows
     * @return list<array{id: string, filetype: string, mime_type: string|null, file_name: string|null, fileshorturl: string, fileslongurl: string, is_audio: bool, summary: string|null, duration_sec: int|null}>
     */
    public function serializeMany(iterable $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if ($row instanceof VoiceNote) {
                $out[] = $this->serialize($row);
            }
        }

        return $out;
    }

    /**
     * @return array{id: string, filetype: string, mime_type: string|null, file_name: string|null, fileshorturl: string, fileslongurl: string, is_audio: bool, summary: string|null, duration_sec: int|null}
     */
    public function serialize(VoiceNote $row): array
    {
        $filetype = strtolower(trim((string) ($row->filetype ?? '')));
        $mime = strtolower(trim((string) ($row->mime_type ?? '')));
        $longUrl = trim((string) ($row->recording_url_long ?? ''));
        $shortUrl = trim((string) ($row->recording_url ?? ''));

        return [
            'id' => (string) $row->id,
            'filetype' => $filetype !== '' ? $filetype : 'bin',
            'mime_type' => $mime !== '' ? $mime : null,
            'file_name' => $row->file_name,
            'fileshorturl' => $shortUrl,
            'fileslongurl' => $longUrl !== '' ? $longUrl : $shortUrl,
            'is_audio' => $this->isAudio($filetype, $mime),
            'summary' => $row->summary,
            'duration_sec' => $row->duration_sec,
        ];
    }

    public function isAudio(string $filetype, string $mime = ''): bool
    {
        if ($mime !== '' && (str_starts_with($mime, 'audio/') || in_array($mime, ['video/mp4', 'application/ogg'], true))) {
            return true;
        }

        return in_array($filetype, ['m4a', 'mp3', 'wav', 'webm', 'ogg', 'oga', 'mp4', 'aac', 'audio'], true);
    }
}
