<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Api\Crm\Concerns\ResolvesCrmApiContext;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\VoiceNote\ContactMediaUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * @group CRM
 *
 * Upload a single contact file (document or image) to storage and return short and long URLs.
 *
 * @authenticated
 */
final class ContactFileController extends Controller
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
    ];

    public function __construct(
        private ContactMediaUploadService $uploadService,
    ) {}

    /**
     * Process contact file
     *
     * Accepts a document or image file, uploads it to storage, and returns short and long URLs.
     * Use `POST /api/crm/media/process-batch` to create the CRM note.
     *
     * @bodyParam user_id int required Must match the authenticated user. Example: 1
     * @bodyParam contact_id string required CRM contact id. Example: 550e8400-e29b-41d4-a716-446655440000
     * @bodyParam file file required Document or image (PDF, DOC, DOCX, or image/*, max ~50 MB).
     */
    public function process(Request $request): JsonResponse
    {
        ['user' => $user, 'tenant' => $tenant] = $this->resolveCrmApiContext($request);

        $data = $request->validate([
            'contact_id' => ['required', 'string'],
            'file' => [
                'required',
                'file',
                'mimes:pdf,doc,docx,jpg,jpeg,png,gif,webp,bmp,tiff,tif,heic,heif',
                'mimetypes:'.implode(',', self::ALLOWED_MIMES).',image/*',
                'max:51200',
            ],
        ]);

        $contactId = trim($data['contact_id']);

        $file = $request->file('file');
        if (! $file || ! $file->isValid()) {
            throw ValidationException::withMessages([
                'file' => ['A valid file is required.'],
            ]);
        }

        if ($file->getSize() > self::MAX_FILE_BYTES) {
            throw ValidationException::withMessages([
                'file' => ['The file may not be greater than 50 MB.'],
            ]);
        }

        try {
            $result = $this->uploadService->upload(
                $tenant,
                $user,
                $contactId,
                $file,
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? (int) $e->getCode() : 502;

            return ApiResponse::error(
                $e->getMessage() !== '' ? $e->getMessage() : 'Contact file processing failed.',
                $status
            );
        } catch (Throwable $e) {
            return ApiResponse::error(
                $e->getMessage() !== '' ? $e->getMessage() : 'Contact file processing failed.',
                502
            );
        }

        return ApiResponse::success($result, 'Contact file processed');
    }
}
