<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Api\Crm\Concerns\ResolvesCrmApiContext;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Contacts\ContactNoteSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * @group CRM
 *
 * Retrieve AI-generated note summaries for a contact identified by phone number.
 *
 * @authenticated
 */
final class ContactNoteSummaryController extends Controller
{
    use ResolvesCrmApiContext;

    public function __construct(
        private ContactNoteSummaryService $summaryService,
    ) {}

    /**
     * List contact note summaries by phone
     *
     * Returns concise summaries (from voice notes or parsed note bodies) with date and time.
     * Works with MyCrmSync, GoHighLevel, and Zoho tenants.
     *
     * @queryParam user_id int required Must match the authenticated user. Example: 1
     * @queryParam phone string required Contact phone number (any common format). Example: +919910023290
     *
     * @response 200 {"success":true,"message":"OK","data":{"contact":{"id":"550e8400-e29b-41d4-a716-446655440000","name":"Jane Doe","phone":"+919910023290","email":"jane@example.com"},"summaries":[{"id":"note-id","summary":"Prospect wants a call Friday at 11:30 AM.","date":"2026-05-06","time":"02:46:31","datetime":"2026-05-06T02:46:31+00:00"}],"meta":{"total":1}}}
     */
    public function byPhone(Request $request): JsonResponse
    {
        ['tenant' => $tenant] = $this->resolveCrmApiContext($request);

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:64'],
        ]);

        try {
            $result = $this->summaryService->summariesByPhone($tenant, $data['phone']);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            return ApiResponse::error(
                $e->getMessage() !== '' ? $e->getMessage() : 'Unable to load contact note summaries.',
                502
            );
        }

        return ApiResponse::success($result);
    }
}
