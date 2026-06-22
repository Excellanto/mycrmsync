<?php

namespace App\Services\VoiceNote;

use App\Models\Tenant;
use App\Models\User;
use App\Models\VoiceNote;
use App\Services\ShortUrl\ShortUrlService;
use Illuminate\Http\UploadedFile;

final class ContactMediaUploadService
{
    public function __construct(
        private VoiceNoteStorageService $storage,
        private MediaFileTypeResolver $fileTypeResolver,
        private ShortUrlService $shortUrls,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function upload(
        Tenant $tenant,
        User $user,
        string $contactId,
        UploadedFile $file,
    ): array {
        $upload = $this->storage->upload((int) $tenant->id, (string) $user->id, $file);
        $filetype = $this->fileTypeResolver->resolve($file);
        $mimeType = $file->getMimeType() ?: null;

        $shortUrl = $this->shortUrls->create(
            $upload['recording_url_long'],
            (int) $tenant->id,
            (int) $user->id,
            'contact_media',
        );

        $voiceNote = VoiceNote::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'contact_id' => $contactId,
            'location_id' => null,
            'file_name' => $upload['file_name'],
            'storage_path' => $upload['storage_path'],
            'filetype' => $filetype,
            'mime_type' => $mimeType,
            'recording_url' => $shortUrl->short_url,
            'recording_url_long' => $upload['recording_url_long'],
            'short_code' => $shortUrl->code,
            'transcription_backend' => null,
            'transcription' => null,
            'summary' => null,
            'note_body' => null,
            'duration_sec' => null,
        ]);

        $this->shortUrls->attachSource($shortUrl, 'contact_media', (string) $voiceNote->id);

        return [
            'voice_note_id' => $voiceNote->id,
            'recording_url' => $shortUrl->short_url,
            'recording_url_long' => $upload['recording_url_long'],
            'file_name' => $upload['file_name'],
            'filetype' => $filetype,
            'mime_type' => $mimeType,
        ];
    }
}
