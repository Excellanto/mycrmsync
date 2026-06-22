<?php

namespace App\Integrations\Connectors\GoHighLevel;

use App\Integrations\Contracts\CrmIntegrationUsersConnector;
use App\Integrations\MysimconnectApi\CrmExternalUserResource;
use App\Integrations\MysimconnectApi\MappedCrmUsersFetchResult;
use App\Models\Integration;
use App\Models\Tenant;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * GoHighLevel / Lead Connector CRM integration: fetch location users and map to MysimConnect resources.
 *
 * @see https://marketplace.gohighlevel.com/docs/ghl/users/get-user-by-location
 */
final class GoHighLevelCrmUsersConnector implements CrmIntegrationUsersConnector
{
    public static function integrationSlug(): string
    {
        return 'gohighlevel';
    }

    public function fetchMappedUsers(array $tenantIntegration, ?Tenant $tenant = null): MappedCrmUsersFetchResult
    {
        [$token, $locationId] = $this->credentialsFromTenantIntegration($tenantIntegration);

        if ($token === '' || $locationId === '') {
            return new MappedCrmUsersFetchResult(
                [],
                'GoHighLevel credentials are incomplete (API key / Location Id). Save the integration settings first.',
                422,
            );
        }

        $body = $this->requestUsersJson($token, $locationId);

        $users = [];

        foreach ($this->iterUserRows($body) as $row) {
            $users[] = $this->mapLeadConnectorUserToMysimconnect($row);
        }

        return new MappedCrmUsersFetchResult(
            $users,
            $users === [] ? 'No CRM users returned for this location.' : null,
            200,
        );
    }

    /**
     * @return array{0: string, 1: string} [accessToken, locationId]
     */
    private function credentialsFromTenantIntegration(array $integration): array
    {
        $values = isset($integration['values']) && is_array($integration['values'])
            ? $integration['values']
            : [];

        $token = '';
        $locationId = '';

        $model = Integration::query()->where('slug', self::integrationSlug())->first();

        if ($model !== null) {
            foreach ($model->fieldSpecs() as $spec) {
                $key = $spec['key'];
                $label = mb_strtolower($spec['label']);
                $raw = $values[$key] ?? null;
                $val = is_string($raw) ? trim($raw) : '';

                if ($val === '') {
                    continue;
                }

                if (str_contains($label, 'location')) {
                    $locationId = $val;
                }

                if (preg_match('/(api|key|token|secret|pit|bearer)/', $label) === 1) {
                    $token = $val;
                }
            }
        }

        if ($token === '') {
            $token = isset($values['api_key']) && is_string($values['api_key'])
                ? trim($values['api_key'])
                : '';
        }

        if ($locationId === '') {
            foreach (['location_id', 'locationid', 'locationId'] as $k) {
                if (! empty($values[$k]) && is_string($values[$k])) {
                    $locationId = trim($values[$k]);

                    break;
                }
            }
        }

        return [$token, $locationId];
    }

    private function requestUsersJson(string $accessToken, string $locationId): array
    {
        $base = rtrim(config('services.gohighlevel.api_base', 'https://services.leadconnectorhq.com'), '/');
        $version = config('services.gohighlevel.api_version', '2023-02-21');

        $response = Http::withToken(trim($accessToken))
            ->withHeaders([
                'Version' => $version,
                'Accept' => 'application/json',
            ])
            ->timeout(45)
            ->connectTimeout(10)
            ->get("{$base}/users/", [
                'locationId' => trim($locationId),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatHttpErrorMessage($response));
        }

        $body = $response->json();

        return is_array($body) ? $body : [];
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    private function iterUserRows(array $body): \Generator
    {
        $rows = data_get($body, 'users');

        if (! is_array($rows)) {
            $rows = data_get($body, 'data');
        }

        if (! is_array($rows)) {
            return;
        }

        foreach ($rows as $row) {
            if (is_array($row)) {
                yield $row;
            }
        }
    }

    /**
     * Map Lead Connector user payload → stable MysimConnect admin CRM user JSON.
     */
    private function mapLeadConnectorUserToMysimconnect(array $row): CrmExternalUserResource
    {
        $name = trim((string) data_get($row, 'name', ''));

        if ($name === '') {
            $name = trim(trim((string) data_get($row, 'firstName', '')).' '.trim((string) data_get($row, 'lastName', '')));
        }

        $role = '';

        foreach (['role', 'type', 'roles'] as $k) {
            $r = data_get($row, $k);

            if (is_array($r) && $r !== []) {
                $role = is_string(reset($r)) ? (string) reset($r) : json_encode($r);

                break;
            }

            if (is_string($r) && $r !== '') {
                $role = $r;

                break;
            }
        }

        return new CrmExternalUserResource(
            id: (string) data_get($row, 'id', ''),
            name: trim($name) !== '' ? trim($name) : '(no name)',
            email: trim((string) data_get($row, 'email', '')),
            phone: trim((string) (data_get($row, 'phone') ?? data_get($row, 'phoneNumber') ?? '')),
            role: $role,
        );
    }

    private function formatHttpErrorMessage(Response $response): string
    {
        $json = $response->json();

        if (is_array($json)) {
            $msg = data_get($json, 'message') ?? data_get($json, 'error') ?? data_get($json, 'msg');

            if (is_string($msg) && $msg !== '') {
                return $msg;
            }
        }

        return 'GoHighLevel API request failed (HTTP '.$response->status().').';
    }
}
