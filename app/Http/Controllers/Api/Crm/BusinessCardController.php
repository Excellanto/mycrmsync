<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Api\Crm\Concerns\ResolvesCrmApiContext;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\BusinessCard\BusinessCardParsingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * @group CRM
 *
 * Parse business card images into CRM contact fields using OpenAI vision.
 *
 * @authenticated
 */
final class BusinessCardController extends Controller
{
    use ResolvesCrmApiContext;

    private const MAX_IMAGE_BYTES = 10_485_760; // 10 MB

    /** @var list<string> */
    private const ALLOWED_MIMES = [
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
        private BusinessCardParsingService $parsingService,
    ) {}

    /**
     * Parse business card
     *
     * Upload a business card photo and receive structured contact fields for the add-contact form.
     *
     * @bodyParam user_id int required Must match the authenticated user. Example: 1
     * @bodyParam image file required Business card photo (JPEG, PNG, WebP, HEIC, max 10 MB).
     * @bodyParam locale string optional BCP 47-style locale hint for parsing (e.g. en_IN). Example: en_IN
     */
    public function parse(Request $request): JsonResponse
    {
        ['tenant' => $tenant] = $this->resolveTenantApiContext($request);

        $data = $request->validate([
            'image' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,gif,webp,bmp,tiff,tif,heic,heif',
                'mimetypes:'.implode(',', self::ALLOWED_MIMES).',image/*',
                'max:10240',
            ],
            'locale' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        $image = $request->file('image');
        if (! $image || ! $image->isValid()) {
            throw ValidationException::withMessages([
                'image' => ['A valid image file is required.'],
            ]);
        }

        if ($image->getSize() > self::MAX_IMAGE_BYTES) {
            throw ValidationException::withMessages([
                'image' => ['The image may not be greater than 10 MB.'],
            ]);
        }

        $locale = trim((string) ($data['locale'] ?? 'en_IN'));
        if ($locale === '') {
            $locale = 'en_IN';
        }

        try {
            $parsed = $this->parsingService->parse(
                (int) $tenant->id,
                $image,
                $locale,
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? (int) $e->getCode() : 502;

            return ApiResponse::error(
                $e->getMessage() !== '' ? $e->getMessage() : 'Business card parsing failed.',
                $status
            );
        } catch (Throwable $e) {
            return ApiResponse::error(
                $e->getMessage() !== '' ? $e->getMessage() : 'Business card parsing failed.',
                502
            );
        }

        return ApiResponse::success($parsed, 'Business card parsed');
    }
}
