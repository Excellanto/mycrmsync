<?php

namespace App\Integrations;

use App\Integrations\MysimconnectApi\MappedCrmUsersFetchResult;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Lists CRM users for a tenant’s persisted integration via the appropriate connector.
 */
final class FetchTenantIntegrationCrmUsers
{
    public function __construct(
        private CrmIntegrationUsersConnectorRegistry $registry,
    ) {}

    /**
     * CRM users mapped for MysimConnect forms (empty if no connector, no integration, or fetch error).
     *
     * @return list<array{id: string, name: string, email: string, phone: string, role: string}>
     */
    public function mappedUsersOrEmpty(Tenant $tenant): array
    {
        $tenant->refresh();
        $integration = $tenant->integration;

        if (! is_array($integration) || blank($integration['slug'] ?? null)) {
            return [];
        }

        $slug = (string) ($integration['slug'] ?? '');
        $connector = $this->registry->connectorFor($slug);

        if ($connector === null) {
            return [];
        }

        try {
            $result = $connector->fetchMappedUsers($integration, $tenant);
            $this->persistIntegrationPullStatusFromResult($tenant, $result);

            return $result->usersAsAdminApiJson();
        } catch (\RuntimeException $e) {
            report($e);
            $this->persistIntegrationPullFailed($tenant);

            return [];
        }
    }

    public function __invoke(Tenant $tenant): JsonResponse
    {
        $tenant->refresh();
        $integration = $tenant->integration;

        if (! is_array($integration) || blank($integration['slug'] ?? null)) {
            return response()->json([
                'users' => [],
                'message' => 'Save a CRM integration for this tenant first.',
            ]);
        }

        $slug = (string) ($integration['slug'] ?? '');
        $connector = $this->registry->connectorFor($slug);

        if ($connector === null) {
            return response()->json([
                'users' => [],
                'message' => 'CRM user listing is not implemented for this integration yet.',
            ]);
        }

        try {
            $result = $connector->fetchMappedUsers($integration, $tenant);
            $this->persistIntegrationPullStatusFromResult($tenant, $result);

            $payload = ['users' => $result->usersAsAdminApiJson()];
            if ($result->message !== null && $result->message !== '') {
                $payload['message'] = $result->message;
            }

            return response()->json($payload, $result->httpStatus);
        } catch (RuntimeException $e) {
            report($e);
            $this->persistIntegrationPullFailed($tenant);

            return response()->json([
                'users' => [],
                'message' => $e->getMessage(),
            ], 502);
        }
    }

    private function persistIntegrationPullStatusFromResult(Tenant $tenant, MappedCrmUsersFetchResult $result): void
    {
        $ok = $result->httpStatus >= 200 && $result->httpStatus < 300;
        $tenant->forceFill(['integration_status' => $ok])->save();
    }

    private function persistIntegrationPullFailed(Tenant $tenant): void
    {
        $tenant->forceFill(['integration_status' => false])->save();
    }
}
