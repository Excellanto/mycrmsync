<?php



namespace App\Http\Controllers\Api\Crm;



use App\Http\Controllers\Controller;

use App\Http\Responses\ApiResponse;

use App\Services\CallLog\CallLogQueryService;

use App\Services\CallLog\CallLogRegistrationService;

use App\Services\CallLog\CallLogUserContextResolver;

use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;



/**

 * @group CRM

 *

 * Register and list device-synced call logs scoped to a user.

 *

 * Register endpoints are open (no Bearer or sync token). Only `user_id` is required.

 * Fetch (`POST /get`) accepts sync token or Bearer token.

 * Legacy GET list requires `Authorization: Bearer <token>`.

 */

final class CallLogController extends Controller

{

    public function __construct(

        private CallLogUserContextResolver $contextResolver,

        private CallLogRegistrationService $registrationService,

        private CallLogQueryService $queryService,

    ) {}



    /**

     * Register a single call log.

     */

    public function register(Request $request): JsonResponse

    {

        ['target' => $target, 'location_id' => $locationId] = $this->contextResolver->resolveForRegistration($request);



        $payload = $request->except(['user_id', 'sync_token']);

        $result = $this->registrationService->register(

            $locationId,

            (string) $target->id,

            $payload,

        );



        $message = $result['created']

            ? 'Call log registered'

            : 'Call log already exists';



        return ApiResponse::success($result, $message);

    }



    /**

     * Register multiple call logs for one user.

     */

    public function registerBulk(Request $request): JsonResponse

    {

        ['target' => $target, 'location_id' => $locationId] = $this->contextResolver->resolveForRegistration($request);



        $validated = $request->validate([

            'calls' => ['required', 'array', 'min:1', 'max:500'],

        ]);



        $summary = $this->registrationService->registerBulk(

            $locationId,

            (string) $target->id,

            $validated['calls'],

        );



        $total = count($validated['calls']);



        return ApiResponse::success(

            $summary,

            "Processed {$total} calls",

        );

    }



    /**

     * Fetch call logs (POST).

     *

     * @bodyParam user_id int required Context user. Example: 123

     * @bodyParam mine boolean When true, only this user's logs. When false, all logs for the user's tenant. Example: true

     * @bodyParam limit int Optional page size 1–500. Example: 50

     * @bodyParam cursor string Optional pagination cursor from a previous response.

     * @bodyParam date_from string Optional start date (YYYY-MM-DD or ISO-8601). Inclusive.

     * @bodyParam date_to string Optional end date (YYYY-MM-DD or ISO-8601). Inclusive.

     */

    public function getCallLogs(Request $request): JsonResponse

    {

        ['actor' => $actor, 'target' => $target, 'location_id' => $locationId] = $this->contextResolver->resolveForFetch($request);



        $validated = $request->validate([
            'mine' => ['required', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
            'cursor' => ['nullable', 'string', 'max:512'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $mine = (bool) $validated['mine'];



        if (! $mine && ! $this->contextResolver->canViewTenantCallLogs($actor, $target)) {

            abort(403, 'You are not authorized to view tenant call logs.');

        }



        $limit = (int) ($validated['limit'] ?? 50);

        $query = $this->queryService->baseQuery($locationId);



        if ($mine) {

            $this->queryService->scopeForUser($query, $target);

        } else {

            $this->queryService->scopeForTenant($query, $target);

        }

        $this->queryService->scopeDateRange(
            $query,
            $validated['date_from'] ?? null,
            $validated['date_to'] ?? null,
        );

        $page = $this->queryService->paginate(

            $query,

            $limit,

            $validated['cursor'] ?? null,

        );



        return ApiResponse::success([

            'scope' => $mine ? 'mine' : 'tenant',

            'user_id' => (string) $target->id,

            'date_from' => $validated['date_from'] ?? null,

            'date_to' => $validated['date_to'] ?? null,

            'items' => $page['items'],

            'next_cursor' => $page['next_cursor'],

        ]);

    }



    /**

     * List call logs for a user (cursor pagination).

     */

    public function index(Request $request): JsonResponse

    {

        ['target' => $target, 'location_id' => $locationId] = $this->contextResolver->resolve($request);



        $validated = $request->validate([

            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],

            'cursor' => ['nullable', 'string', 'max:512'],

        ]);



        $limit = (int) ($validated['limit'] ?? 50);

        $query = $this->queryService->baseQuery($locationId);

        $this->queryService->scopeForUser($query, $target);



        $page = $this->queryService->paginate(

            $query,

            $limit,

            $validated['cursor'] ?? null,

        );



        return ApiResponse::success([

            'items' => $page['items'],

            'next_cursor' => $page['next_cursor'],

        ]);

    }

}


