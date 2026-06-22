<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Api\Crm\Concerns\ResolvesCrmApiContext;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\VoiceNote\ContactMediaBatchProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * @group CRM
 *
 * Batch upload contact media and create a single CRM note.
 *
 * @authenticated
 */
final class ContactMediaBatchController extends Controller
{
    use ResolvesCrmApiContext;

    private const MAX_FILE_BYTES = 52_428_800; // ~50 MB

    /** @var list<string> */
    private const ALLOWED_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/bmp',
        'image/tiff',
        'image/heic',
        'image/heif',
        'application/octet-stream',
        'audio/*',
        'video/mp4',
        'application/ogg',
    ];

    public function __construct(
        private ContactMediaBatchProcessingService $processingService,
    ) {}

    /**
     * Process contact media batch
     *
     * Uploads multiple files (and/or reuses prior voice_note_ids), runs AI on audio files,
     * builds one combined note body, and creates a single CRM contact note.
     *
     * @bodyParam user_id int required Must match the authenticated user. Example: 1
     * @bodyParam contact_id string required CRM contact id. Example: 550e8400-e29b-41d4-a716-446655440000
     * @bodyParam files file[] optional New files to upload (audio, PDF, DOC, DOCX, images). At least one file or voice_note_id is required.
     * @bodyParam voice_note_ids string[] optional Existing voice_note ids from prior uploads for this contact. Example: ["550e8400-e29b-41d4-a716-446655440000"]
     * @bodyParam existing_note_text string optional Text already in the note box; prepended to the CRM note.
     */
    public function process(Request $request): JsonResponse
    {
        ['user' => $user, 'tenant' => $tenant] = $this->resolveCrmApiContext($request);

        $data = $request->validate([
            'contact_id' => ['required', 'string'],
            'files' => ['sometimes', 'array'],
            'files.*' => [
                'file',
                'mimes:pdf,doc,docx,jpg,jpeg,png,gif,webp,bmp,tiff,tif,heic,heif,m4a,mp3,wav,webm,ogg,oga,mp4',
                'mimetypes:'.implode(',', self::ALLOWED_MIMES).',image/*',
                'max:51200',
            ],
            'voice_note_ids' => ['sometimes', 'array'],
            'voice_note_ids.*' => ['string', 'uuid'],
            'existing_note_text' => ['sometimes', 'nullable', 'string'],
        ]);

        $contactId = trim($data['contact_id']);
        $files = $this->normalizeUploadedFiles($request->file('files'));
        $voiceNoteIds = array_values($data['voice_note_ids'] ?? []);

        foreach ($files as $index => $file) {
            if ($file->getSize() > self::MAX_FILE_BYTES) {
                throw ValidationException::withMessages([
                    "files.{$index}" => ['Each file may not be greater than 50 MB.'],
                ]);
            }
        }

        try {
            $result = $this->processingService->process(
                $tenant,
                $user,
                $contactId,
                $files,
                $voiceNoteIds,
                $data['existing_note_text'] ?? null,
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? (int) $e->getCode() : 502;

            return ApiResponse::error(
                $e->getMessage() !== '' ? $e->getMessage() : 'Contact media batch processing failed.',
                $status
            );
        } catch (Throwable $e) {
            return ApiResponse::error(
                $e->getMessage() !== '' ? $e->getMessage() : 'Contact media batch processing failed.',
                502
            );
        }

        return ApiResponse::success($result, 'Contact media batch processed');
    }

    /**
     * @return list<UploadedFile>
     */
    private function normalizeUploadedFiles(mixed $files): array
    {
        if ($files === null) {
            return [];
        }

        if ($files instanceof UploadedFile) {
            return $files->isValid() ? [$files] : [];
        }

        if (! is_array($files)) {
            return [];
        }

        return array_values(array_filter(
            $files,
            fn (mixed $file) => $file instanceof UploadedFile && $file->isValid()
        ));
    }
}
