<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Api\Crm\Concerns\ResolvesCrmApiContext;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Contacts\ContactDetailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * @group CRM
 *
 * Retrieve a single contact with call and note stats.
 *
 * @authenticated
 */
final class ContactController extends Controller
{
    use ResolvesCrmApiContext;

    public function __construct(
        private ContactDetailService $contactDetailService,
    ) {}

    /**
     * Get contact
     *
     * Returns the normalized contact plus call stats for the authenticated user.
     *
     * @queryParam user_id int required Must match the authenticated user. Example: 1
     * @queryParam contactId string optional CRM contact id. Required when `phone` is omitted. Example: 550e8400-e29b-41d4-a716-446655440000
     * @queryParam phone string optional Contact phone number. Required when `contactId` is omitted. Example: +919910023290
     *
     * @response 200 {"success":true,"message":"OK","data":{"contact":{"id":"550e8400-e29b-41d4-a716-446655440000","name":"Jane Doe","phone":"+919910023290","email":"jane@example.com"},"calls":{"total_dialed":4,"total_talk_time":620,"total_received":3,"total_notes":2}}}
     */
    public function show(Request $request): JsonResponse
    {
        ['user' => $user, 'tenant' => $tenant] = $this->resolveCrmApiContext($request);

        $data = $request->validate([
            'contactId' => ['nullable', 'string'],
            'contact' => ['nullable', 'string'],
            'contact_id' => ['nullable', 'string'],
            'contactid' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:64'],
        ]);

        $contactId = trim((string) (
            $data['contactId']
                ?? $data['contact']
                ?? $data['contact_id']
                ?? $data['contactid']
                ?? ''
        ));
        $phone = trim((string) ($data['phone'] ?? ''));

        try {
            $result = $this->contactDetailService->detailForRequest($tenant, $user, $contactId, $phone);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            return ApiResponse::error(
                $e->getMessage() !== '' ? $e->getMessage() : 'Unable to load contact.',
                502
            );
        }

        return ApiResponse::success($result);
    }
}
