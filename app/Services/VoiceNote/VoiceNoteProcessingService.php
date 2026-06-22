<?php

namespace App\Services\VoiceNote;

use App\Models\Tenant;
use App\Models\User;
use App\Models\VoiceNote;
use App\Services\ShortUrl\ShortUrlService;
use Illuminate\Http\UploadedFile;

final class VoiceNoteProcessingService
{
    public function __construct(
        private VoiceNoteStorageService $storage,
        private AudioTranscriptionService $transcription,
        private VoiceNoteSummaryService $summary,
        private VoiceNoteBodyFormatter $bodyFormatter,
        private MediaFileTypeResolver $fileTypeResolver,
        private ShortUrlService $shortUrls,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function process(
        Tenant $tenant,
        User $user,
        string $contactId,
        UploadedFile $audio,
        ?string $existingNoteText = null,
    ): array {
        $upload = $this->storage->upload((int) $tenant->id, (string) $user->id, $audio);
        $filetype = $this->fileTypeResolver->resolve($audio);
        $mimeType = $audio->getMimeType() ?: null;

        $shortUrl = $this->shortUrls->create(
            $upload['recording_url_long'],
            (int) $tenant->id,
            (int) $user->id,
            'voice_note',
        );

        $transcriptionResult = $this->transcription->transcribe((int) $tenant->id, $audio);

        $summaryText = $this->summary->summarize(
            (int) $tenant->id,
            $transcriptionResult['transcription']
        );

        $noteBody = $this->bodyFormatter->format(
            $summaryText,
            $transcriptionResult['transcription'],
            $existingNoteText
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
            'transcription_backend' => $transcriptionResult['transcription_backend'],
            'transcription' => $transcriptionResult['transcription'],
            'summary' => $summaryText,
            'note_body' => $noteBody,
            'duration_sec' => $transcriptionResult['duration_sec'],
        ]);

        $this->shortUrls->attachSource($shortUrl, 'voice_note', (string) $voiceNote->id);

        return [
            'voice_note_id' => $voiceNote->id,
            'recording_url' => $shortUrl->short_url,
            'recording_url_long' => $upload['recording_url_long'],
            'file_name' => $upload['file_name'],
            'filetype' => $filetype,
            'mime_type' => $mimeType,
            'transcription_backend' => $transcriptionResult['transcription_backend'],
            'transcription_engine_label' => $transcriptionResult['transcription_engine_label'],
            'transcription' => $transcriptionResult['transcription'],
            'summary' => $summaryText,
            'note_body' => $noteBody,
            'duration_sec' => $transcriptionResult['duration_sec'],
        ];
    }
}
