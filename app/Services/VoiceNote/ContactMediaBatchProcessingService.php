<?php

namespace App\Services\VoiceNote;

use App\Models\Tenant;
use App\Models\User;
use App\Models\VoiceNote;
use App\Services\ShortUrl\ShortUrlService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ContactMediaBatchProcessingService
{
    public function __construct(
        private VoiceNoteStorageService $storage,
        private AudioTranscriptionService $transcription,
        private VoiceNoteSummaryService $summary,
        private MediaFileTypeResolver $fileTypeResolver,
        private ContactMediaNoteBodyFormatter $noteBodyFormatter,
        private CrmContactNoteCreator $crmNoteCreator,
        private ShortUrlService $shortUrls,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     * @param  list<string>  $voiceNoteIds
     * @return array<string, mixed>
     */
    public function process(
        Tenant $tenant,
        User $user,
        string $contactId,
        array $files,
        array $voiceNoteIds,
        ?string $existingNoteText = null,
    ): array {
        $files = array_values(array_filter($files, fn (UploadedFile $file) => $file->isValid()));
        $voiceNoteIds = array_values(array_unique(array_filter(array_map(
            fn (mixed $id) => trim((string) $id),
            $voiceNoteIds
        ))));

        if ($files === [] && $voiceNoteIds === []) {
            throw ValidationException::withMessages([
                'files' => ['Provide at least one file or voice_note_id.'],
            ]);
        }

        $batchId = (string) Str::uuid();
        $processedFiles = [];

        foreach ($files as $file) {
            $processedFiles[] = $this->storeUploadedFile(
                $tenant,
                $user,
                $contactId,
                $batchId,
                $file,
            );
        }

        $existingRows = $this->loadExistingVoiceNotes($tenant, $user, $contactId, $voiceNoteIds);

        foreach ($existingRows as $row) {
            $row->update(['batch_id' => $batchId]);
        }

        $allRows = collect($processedFiles)
            ->pluck('model')
            ->merge($existingRows)
            ->values();

        $audioSections = [];

        foreach ($allRows as $row) {
            if ($this->rowHasVoiceContent($row)) {
                $audioSections[] = [
                    'summary' => (string) ($row->summary ?? ''),
                    'transcription' => (string) ($row->transcription ?? ''),
                ];
            }
        }

        $noteBody = $this->noteBodyFormatter->formatBatch(
            $existingNoteText,
            $audioSections,
        );

        if (trim($noteBody) === '') {
            if ($allRows->isEmpty()) {
                throw ValidationException::withMessages([
                    'files' => ['Unable to build a CRM note body from the provided media.'],
                ]);
            }

            $noteBody = ' ';
        }

        $crmNote = $this->crmNoteCreator->create($tenant, $user, $contactId, $noteBody);
        $crmNoteId = is_array($crmNote) ? (string) ($crmNote['id'] ?? '') : '';

        if ($crmNoteId !== '') {
            VoiceNote::query()
                ->whereIn('id', $allRows->pluck('id')->all())
                ->update(['crm_note_id' => $crmNoteId]);
        }

        return [
            'batch_id' => $batchId,
            'note_body' => $noteBody,
            'crm_note_id' => $crmNoteId !== '' ? $crmNoteId : null,
            'crm_note' => $crmNote,
            'files' => $allRows->map(fn (VoiceNote $row) => $this->serializeRow($row))->values()->all(),
        ];
    }

    /**
     * @return array{model: VoiceNote, filetype: string}
     */
    private function storeUploadedFile(
        Tenant $tenant,
        User $user,
        string $contactId,
        string $batchId,
        UploadedFile $file,
    ): array {
        $upload = $this->storage->upload((int) $tenant->id, (string) $user->id, $file);
        $filetype = $this->fileTypeResolver->resolve($file);
        $mimeType = $file->getMimeType() ?: null;
        $isAudio = $this->fileTypeResolver->isAudio($file);

        $shortUrl = $this->shortUrls->create(
            $upload['recording_url_long'],
            (int) $tenant->id,
            (int) $user->id,
            'contact_media_batch',
        );

        $transcriptionResult = null;
        $summaryText = null;
        $noteBody = null;

        if ($isAudio) {
            $transcriptionResult = $this->transcription->transcribe((int) $tenant->id, $file);
            $summaryText = $this->summary->summarize(
                (int) $tenant->id,
                $transcriptionResult['transcription']
            );
        }

        $voiceNote = VoiceNote::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'contact_id' => $contactId,
            'batch_id' => $batchId,
            'location_id' => null,
            'file_name' => $upload['file_name'],
            'storage_path' => $upload['storage_path'],
            'filetype' => $filetype,
            'mime_type' => $mimeType,
            'recording_url' => $shortUrl->short_url,
            'recording_url_long' => $upload['recording_url_long'],
            'short_code' => $shortUrl->code,
            'transcription_backend' => $transcriptionResult['transcription_backend'] ?? null,
            'transcription' => $transcriptionResult['transcription'] ?? null,
            'summary' => $summaryText,
            'note_body' => $noteBody,
            'duration_sec' => $transcriptionResult['duration_sec'] ?? null,
        ]);

        $this->shortUrls->attachSource($shortUrl, 'contact_media_batch', (string) $voiceNote->id);

        return [
            'model' => $voiceNote->refresh(),
            'filetype' => $filetype,
        ];
    }

    /**
     * @param  list<string>  $voiceNoteIds
     * @return Collection<int, VoiceNote>
     */
    private function loadExistingVoiceNotes(
        Tenant $tenant,
        User $user,
        string $contactId,
        array $voiceNoteIds,
    ): Collection {
        if ($voiceNoteIds === []) {
            return collect();
        }

        $rows = VoiceNote::query()
            ->whereIn('id', $voiceNoteIds)
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('contact_id', $contactId)
            ->get();

        if ($rows->count() !== count($voiceNoteIds)) {
            throw ValidationException::withMessages([
                'voice_note_ids' => ['One or more voice_note_ids are invalid for this user and contact.'],
            ]);
        }

        return $rows->values();
    }

    private function rowHasVoiceContent(VoiceNote $row): bool
    {
        return trim((string) ($row->summary ?? '')) !== ''
            || trim((string) ($row->transcription ?? '')) !== '';
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRow(VoiceNote $row): array
    {
        return [
            'voice_note_id' => $row->id,
            'file_name' => $row->file_name,
            'filetype' => $row->filetype,
            'mime_type' => $row->mime_type,
            'recording_url' => $row->recording_url,
            'recording_url_long' => $row->recording_url_long,
            'transcription' => $row->transcription,
            'summary' => $row->summary,
            'duration_sec' => $row->duration_sec,
        ];
    }
}
