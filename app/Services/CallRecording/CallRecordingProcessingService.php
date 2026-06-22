<?php

namespace App\Services\CallRecording;

use App\Models\CallLog;
use App\Models\CallRecording;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ShortUrl\ShortUrlService;
use App\Services\VoiceNote\AudioTranscriptionService;
use App\Services\VoiceNote\MediaFileTypeResolver;
use App\Services\VoiceNote\VoiceNoteStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class CallRecordingProcessingService
{
    public function __construct(
        private VoiceNoteStorageService $storage,
        private AudioTranscriptionService $transcription,
        private CallRecordingAnalysisService $analysis,
        private MediaFileTypeResolver $fileTypeResolver,
        private ShortUrlService $shortUrls,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function process(
        Tenant $tenant,
        User $user,
        UploadedFile $audio,
        ?string $callLogId = null,
        ?string $contactId = null,
        ?string $language = null,
        bool $storeRecording = true,
    ): array {
        if ($callLogId !== null) {
            $this->assertCallLogAccessible($callLogId, $user);
        }

        $filetype = $this->fileTypeResolver->resolve($audio);
        $mimeType = $audio->getMimeType() ?: null;

        $upload = null;
        $shortUrl = null;

        if ($storeRecording) {
            $upload = $this->storage->upload((int) $tenant->id, (string) $user->id, $audio);
            $shortUrl = $this->shortUrls->create(
                $upload['recording_url_long'],
                (int) $tenant->id,
                (int) $user->id,
                'call_recording',
            );
        }

        $transcriptionResult = $this->transcription->transcribe(
            (int) $tenant->id,
            $audio,
            $language,
        );

        $analysisResult = $this->analysis->analyze(
            (int) $tenant->id,
            $transcriptionResult['transcription'],
        );

        $callRecording = CallRecording::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'call_log_id' => $callLogId,
            'contact_id' => $contactId !== null && trim($contactId) !== '' ? trim($contactId) : null,
            'file_name' => $upload['file_name'] ?? $audio->getClientOriginalName(),
            'storage_path' => $upload['storage_path'] ?? null,
            'filetype' => $filetype,
            'mime_type' => $mimeType,
            'recording_url' => $shortUrl?->short_url,
            'recording_url_long' => $upload['recording_url_long'] ?? null,
            'short_code' => $shortUrl?->code,
            'transcription_backend' => $transcriptionResult['transcription_backend'],
            'transcription' => $transcriptionResult['transcription'],
            'summary' => $analysisResult['summary'],
            'sentiment' => $analysisResult['sentiment'],
            'duration_sec' => $transcriptionResult['duration_sec'],
            'status' => CallRecording::STATUS_COMPLETED,
        ]);

        if ($shortUrl !== null) {
            $this->shortUrls->attachSource($shortUrl, 'call_recording', (string) $callRecording->id);
        }

        return $this->formatResult($callRecording, $transcriptionResult['transcription_engine_label']);
    }

    /**
     * @return array<string, mixed>
     */
    public function formatResult(CallRecording $recording, ?string $transcriptionEngineLabel = null): array
    {
        return [
            'call_recording_id' => $recording->id,
            'call_log_id' => $recording->call_log_id,
            'contact_id' => $recording->contact_id,
            'duration_sec' => $recording->duration_sec,
            'transcription' => $recording->transcription,
            'summary' => $recording->summary,
            'sentiment' => $recording->sentiment,
            'transcription_backend' => $recording->transcription_backend,
            'transcription_engine_label' => $transcriptionEngineLabel,
            'recording_url' => $recording->recording_url,
            'recording_url_long' => $recording->recording_url_long,
            'file_name' => $recording->file_name,
            'filetype' => $recording->filetype,
            'mime_type' => $recording->mime_type,
            'status' => $recording->status,
            'created_at' => $recording->created_at?->toIso8601String(),
        ];
    }

    private function assertCallLogAccessible(string $callLogId, User $user): void
    {
        $exists = CallLog::query()
            ->whereKey($callLogId)
            ->where('user_id', (string) $user->id)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'call_log_id' => ['The call log was not found for this user.'],
            ]);
        }
    }
}
