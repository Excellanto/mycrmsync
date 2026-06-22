<?php

namespace App\Services\CallLog;

use App\Integrations\Connectors\GoHighLevel\GoHighLevelApiClient;
use App\Integrations\Connectors\GoHighLevel\GoHighLevelCrmUsersConnector;
use App\Integrations\Connectors\Zoho\ZohoCrmUsersConnector;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

final class CallLogUserContextResolver
{
    public function __construct(
        private GoHighLevelApiClient $ghl,
        private CallLogSyncTokenService $syncTokenService,
    ) {}

    /**
     * Open registration: validates user_id only (no Bearer or sync token).
     *
     * @return array{target: User, tenant: Tenant, location_id: string}
     */
    public function resolveForRegistration(Request $request): array
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $target = User::query()
            ->with('tenant')
            ->whereKey((int) $data['user_id'])
            ->firstOrFail();

        return $this->tenantContext($target);
    }

    /**
     * Authenticated read: Sanctum Bearer + user_id authorization.
     *
     * @return array{actor: User, target: User, tenant: Tenant, location_id: string}
     */
    public function resolve(Request $request): array
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(401, 'Unauthenticated.');
        }

        $target = User::query()
            ->with('tenant')
            ->whereKey((int) $data['user_id'])
            ->firstOrFail();

        if (! $this->canActOnUser($actor, $target)) {
            abort(403, 'You are not authorized to access call logs for this user.');
        }

        $context = $this->tenantContext($target);
        $context['actor'] = $actor;

        return $context;
    }

    /**
     * Fetch call logs: sync token or Sanctum Bearer + user_id authorization.
     *
     * @return array{actor: User, target: User, tenant: Tenant, location_id: string}
     */
    public function resolveForFetch(Request $request): array
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $target = User::query()
            ->with('tenant')
            ->whereKey((int) $data['user_id'])
            ->firstOrFail();

        $actor = $this->resolveFetchActor($request, $target);
        $context = $this->tenantContext($target);
        $context['actor'] = $actor;

        return $context;
    }

    public function canViewTenantCallLogs(User $actor, User $target): bool
    {
        if ($actor->isMaster()) {
            return true;
        }

        return $target->tenant_id !== null
            && (int) $actor->tenant_id === (int) $target->tenant_id;
    }

    public function resolveFetchActor(Request $request, User $target): User
    {
        $syncToken = $this->extractSyncToken($request);
        if ($syncToken !== null) {
            if (! $this->syncTokenService->validate($target, $syncToken)) {
                abort(401, 'Invalid call sync token.');
            }

            return $target;
        }

        $bearer = $request->bearerToken();
        if (is_string($bearer) && $bearer !== '') {
            $accessToken = PersonalAccessToken::findToken($bearer);
            $actor = $accessToken?->tokenable;

            if ($actor instanceof User && $this->canActOnUser($actor, $target)) {
                return $actor;
            }
        }

        abort(401, 'Call sync token or Bearer token is required.');
    }

    public function canActOnUser(User $actor, User $target): bool
    {
        if ((int) $actor->id === (int) $target->id) {
            return true;
        }

        if ($actor->isMaster()) {
            return true;
        }

        return $actor->can('update', $target);
    }

    public function resolveLocationId(Tenant $tenant): string
    {
        $integration = is_array($tenant->integration) ? $tenant->integration : [];
        $slug = (string) ($integration['slug'] ?? '');

        if ($slug === GoHighLevelCrmUsersConnector::integrationSlug()) {
            return $this->ghl->defaultLocationId($tenant);
        }

        if ($slug === ZohoCrmUsersConnector::integrationSlug()) {
            $values = is_array($integration['values'] ?? null) ? $integration['values'] : [];

            foreach (['org_id', 'organization_id', 'zoho_org_id', 'zgid'] as $key) {
                if (! empty($values[$key]) && is_string($values[$key])) {
                    return trim($values[$key]);
                }
            }
        }

        return 'tenant:'.$tenant->id;
    }

    /**
     * @return array{target: User, tenant: Tenant, location_id: string}
     */
    private function tenantContext(User $target): array
    {
        $tenant = $target->tenant;
        if ($tenant === null) {
            throw ValidationException::withMessages([
                'user_id' => ['The selected user is not linked to a tenant.'],
            ]);
        }

        return [
            'target' => $target,
            'tenant' => $tenant,
            'location_id' => $this->resolveLocationId($tenant),
        ];
    }

    private function extractSyncToken(Request $request): ?string
    {
        $header = (string) config('auth.call_log_sync.header', 'X-Call-Sync-Token');
        $fromHeader = $request->header($header);

        if (is_string($fromHeader) && trim($fromHeader) !== '') {
            return trim($fromHeader);
        }

        $fromBody = $request->input('sync_token');
        if (is_string($fromBody) && trim($fromBody) !== '') {
            return trim($fromBody);
        }

        return null;
    }
}
