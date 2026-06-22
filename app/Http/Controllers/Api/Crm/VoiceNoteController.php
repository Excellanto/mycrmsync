<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Api\Crm\Concerns\ResolvesCrmApiContext;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\VoiceNote\VoiceNoteProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * @group CRM
 *
 * Process recorded voice notes: upload to storage, transcribe, summarize, and return a CRM-ready note body.
 *
 * @authenticated
 */
final class VoiceNoteController extends Controller
{
    use ResolvesCrmApiContext;

    private const MAX_AUDIO_BYTES = 52_428_800; // ~50 MB

    public function __construct(
        private VoiceNoteProcessingService $processingService,
    ) {}

    /**
     * Process voice note
     *
     * Accepts recorded audio, uploads to storage, transcribes, summarizes, and returns a ready-to-paste CRM note body.
     * Use `POST /api/crm/media/process-batch` to create the CRM note with attachments.
     *
     * @bodyParam user_id int required Must match the authenticated user. Example: 1
     * @bodyParam contact_id string required CRM contact id. Example: 550e8400-e29b-41d4-a716-446655440000
     * @bodyParam audio file required Recorded audio (AAC/MPEG-4 or other audio/*, max ~50 MB).
     * @bodyParam existing_note_text string optional Text already in the note box; included in the returned note_body preview.
     */
    public function process(Request $request): JsonResponse
    {
        ['user' => $user, 'tenant' => $tenant] = $this->resolveCrmApiContext($request);

        $data = $request->validate([
            'contact_id' => ['required', 'string'],
            'audio' => ['required', 'file', 'mimetypes:audio/*,video/mp4,application/octet-stream,application/ogg', 'max:51200'],
            'existing_note_text' => ['sometimes', 'nullable', 'string'],
        ]);

        $contactId = trim($data['contact_id']);

        $audio = $request->file('audio');
        if (! $audio || ! $audio->isValid()) {
            throw ValidationException::withMessages([
                'audio' => ['A valid audio file is required.'],
            ]);
        }

        if ($audio->getSize() > self::MAX_AUDIO_BYTES) {
            throw ValidationException::withMessages([
                'audio' => ['The audio file may not be greater than 50 MB.'],
            ]);
        }

        try {
            $result = $this->processingService->process(
                $tenant,
                $user,
                $contactId,
                $audio,
                $data['existing_note_text'] ?? null,
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? (int) $e->getCode() : 502;

            return ApiResponse::error(
                $e->getMessage() !== '' ? $e->getMessage() : 'Voice note processing failed.',
                $status
            );
        } catch (Throwable $e) {
            return ApiResponse::error(
                $e->getMessage() !== '' ? $e->getMessage() : 'Voice note processing failed.',
                502
            );
        }

        return ApiResponse::success($result, 'Voice note processed');
    }
}
