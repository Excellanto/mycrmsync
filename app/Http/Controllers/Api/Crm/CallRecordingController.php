<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Api\Crm\Concerns\ResolvesCrmApiContext;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CallRecording;
use App\Services\CallRecording\CallRecordingProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * @group CRM
 *
 * Transcribe call recordings and return transcription, summary, and sentiment analysis.
 *
 * @authenticated
 */
final class CallRecordingController extends Controller
{
    use ResolvesCrmApiContext;

    private const MAX_AUDIO_BYTES = 52_428_800; // ~50 MB

    public function __construct(
        private CallRecordingProcessingService $processingService,
    ) {}

    /**
     * Transcribe call recording
     *
     * Accepts a call audio file, transcribes it, summarizes the conversation, and analyzes sentiment.
     *
     * @bodyParam user_id int required Must match the authenticated user. Example: 1
     * @bodyParam audio file required Call recording (mp3, m4a, wav, ogg, etc.; max ~50 MB).
     * @bodyParam call_log_id string optional UUID of an existing call log to link this recording to. Example: 550e8400-e29b-41d4-a716-446655440000
     * @bodyParam contact_id string optional CRM contact id for context. Example: 1303041000000523005
     * @bodyParam language string optional ISO-639-1 language hint for transcription (e.g. en, es). Example: en
     * @bodyParam store_recording boolean optional Persist the audio file to storage (default true). Example: true
     */
    public function transcribe(Request $request): JsonResponse
    {
        ['user' => $user, 'tenant' => $tenant] = $this->resolveCrmApiContext($request);

        $data = $request->validate([
            'audio' => ['required', 'file', 'mimetypes:audio/*,video/mp4,application/octet-stream,application/ogg', 'max:51200'],
            'call_log_id' => ['sometimes', 'nullable', 'uuid'],
            'contact_id' => ['sometimes', 'nullable', 'string'],
            'language' => ['sometimes', 'nullable', 'string', 'size:2'],
            'store_recording' => ['sometimes', 'boolean'],
        ]);

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

        $storeRecording = array_key_exists('store_recording', $data)
            ? (bool) $data['store_recording']
            : true;

        try {
            $result = $this->processingService->process(
                $tenant,
                $user,
                $audio,
                isset($data['call_log_id']) ? trim((string) $data['call_log_id']) : null,
                isset($data['contact_id']) ? trim((string) $data['contact_id']) : null,
                isset($data['language']) ? trim((string) $data['language']) : null,
                $storeRecording,
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? (int) $e->getCode() : 502;

            return ApiResponse::error(
                $e->getMessage() !== '' ? $e->getMessage() : 'Call recording processing failed.',
                $status
            );
        } catch (Throwable $e) {
            return ApiResponse::error(
                $e->getMessage() !== '' ? $e->getMessage() : 'Call recording processing failed.',
                502
            );
        }

        return ApiResponse::success($result, 'Call recording processed');
    }

    /**
     * Get call recording
     *
     * Retrieve a previously processed call recording by id.
     *
     * @queryParam user_id int required Must match the authenticated user. Example: 1
     */
    public function show(Request $request, string $callRecordingId): JsonResponse
    {
        ['user' => $user, 'tenant' => $tenant] = $this->resolveCrmApiContext($request);

        $recording = CallRecording::query()
            ->whereKey($callRecordingId)
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->first();

        if ($recording === null) {
            return ApiResponse::error('Call recording not found.', 404);
        }

        return ApiResponse::success(
            $this->processingService->formatResult($recording),
            'Call recording retrieved'
        );
    }
}
