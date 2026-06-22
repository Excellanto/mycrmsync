<?php

namespace App\Integrations\Connectors\Zoho;

use App\Integrations\Contracts\CrmIntegrationUsersConnector;
use App\Integrations\MysimconnectApi\CrmExternalUserResource;
use App\Integrations\MysimconnectApi\MappedCrmUsersFetchResult;
use App\Models\Integration;
use App\Models\Tenant;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Zoho CRM: list org users and map to MysimConnect external user resources.
 *
 * Every Zoho CRM API call runs only after Zoho Accounts has produced current tokens (authorization_code
 * exchange and/or refresh_token grant) in this request when client id, secret, and refresh are available.
 *
 * @see https://www.zoho.com/crm/developer/docs/api/v8/get-users.html
 * @see https://www.zoho.com/assist/api/generate-access-token.html
 */
final class ZohoCrmUsersConnector implements CrmIntegrationUsersConnector
{
    public static function integrationSlug(): string
    {
        return 'zoho';
    }

    public function fetchMappedUsers(array $tenantIntegration, ?Tenant $tenant = null): MappedCrmUsersFetchResult
    {
        try {
            [$credentials, $tokenHint] = $this->authorizedCrmCredentials($tenantIntegration, $tenant);
        } catch (RuntimeException $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 502;

            return new MappedCrmUsersFetchResult([], $e->getMessage(), $status);
        }

        try {
            $users = $this->fetchAllUsers(
                $credentials['access_token'],
                $credentials['crm_api_base'],
                $credentials['refresh_token'],
                $credentials['client_id'],
                $credentials['client_secret'],
            );
        } catch (RuntimeException $e) {
            return new MappedCrmUsersFetchResult(
                [],
                $e->getMessage(),
                502,
            );
        }

        $emptyMsg = $users === [] ? 'No CRM users returned for this Zoho organization.' : null;
        $message = match (true) {
            $users !== [] => null,
            $tokenHint !== null && $emptyMsg !== null => $tokenHint.' '.$emptyMsg,
            $tokenHint !== null => $tokenHint,
            default => $emptyMsg,
        };

        return new MappedCrmUsersFetchResult($users, $message, 200);
    }

    /**
     * Resolve a current Zoho CRM access token and CRM API base URL for any Zoho-backed app API call.
     *
     * @return array{0: array{
     *     access_token: string,
     *     refresh_token: string,
     *     client_id: string,
     *     client_secret: string,
     *     crm_api_base: string,
     *     authorization_code: string,
     *     redirect_uri: string,
     * }, 1: string|null}
     */
    public function authorizedCrmCredentials(array $tenantIntegration, ?Tenant $tenant = null): array
    {
        $credentials = $this->credentialsFromTenantIntegration($tenantIntegration);
        $tokenHint = null;
        /** True if we already called Zoho Accounts in this request (code exchange or refresh); skip redundant refresh before CRM. */
        $tokensFromAccountsThisRequest = false;

        /** Prefer a new authorization code over a stored access token (avoids skipping exchange when the access field still holds an old token). */
        if ($credentials['authorization_code'] !== '') {
            if ($credentials['client_id'] === '' || $credentials['client_secret'] === '' || $credentials['redirect_uri'] === '') {
                throw new RuntimeException(
                    'Zoho authorization code exchange requires client id, client secret, and redirect URI. Set them on the Zoho integration or via ZOHO_OAUTH_CLIENT_ID, ZOHO_OAUTH_CLIENT_SECRET, and ZOHO_OAUTH_REDIRECT_URI.',
                    422,
                );
            }

            $exchanged = $this->exchangeAuthorizationCode(
                $credentials['authorization_code'],
                $credentials['client_id'],
                $credentials['client_secret'],
                $credentials['redirect_uri'],
            );

            $credentials['access_token'] = $exchanged['access_token'];
            if ($exchanged['refresh_token'] !== '') {
                $credentials['refresh_token'] = $exchanged['refresh_token'];
            }
            if ($exchanged['api_domain'] !== '') {
                $credentials['crm_api_base'] = rtrim($exchanged['api_domain'], '/');
            }

            if ($tenant !== null) {
                $this->persistZohoTokensAfterCodeExchange(
                    $tenant,
                    $exchanged['access_token'],
                    $exchanged['refresh_token'],
                    $exchanged['api_domain'],
                );
                $tokenHint = 'OAuth access and refresh tokens were saved from your authorization code before Zoho CRM API calls.';
            } else {
                $tokenHint = 'OAuth tokens were obtained from your authorization code before Zoho CRM API calls.';
            }

            $tokensFromAccountsThisRequest = true;
        }

        if (
            $credentials['access_token'] === ''
            && $this->canRefresh($credentials['refresh_token'], $credentials['client_id'], $credentials['client_secret'])
        ) {
            $pair = $this->refreshAccessTokenPair(
                $credentials['refresh_token'],
                $credentials['client_id'],
                $credentials['client_secret'],
            );

            $credentials['access_token'] = $pair['access_token'];
            if ($pair['refresh_token'] !== null && $pair['refresh_token'] !== '') {
                $credentials['refresh_token'] = $pair['refresh_token'];
            }
            if ($tenant !== null) {
                $this->persistZohoAccessTokenOnly($tenant, $pair['access_token'], $pair['refresh_token']);
            }
            $tokenHint = 'Access token (and refresh token if returned by Zoho) were issued from your refresh token before Zoho CRM API calls.';
            $tokensFromAccountsThisRequest = true;
        }

        if ($credentials['access_token'] === '') {
            throw new RuntimeException(
                'Zoho credentials are incomplete. Provide an OAuth access token, a refresh token (with client credentials), or an authorization code with client id, client secret, and redirect URI.',
                422,
            );
        }

        if (
            $this->canRefresh($credentials['refresh_token'], $credentials['client_id'], $credentials['client_secret'])
            && ! $tokensFromAccountsThisRequest
        ) {
            $pair = $this->refreshAccessTokenPair(
                $credentials['refresh_token'],
                $credentials['client_id'],
                $credentials['client_secret'],
            );

            $credentials['access_token'] = $pair['access_token'];
            if ($pair['refresh_token'] !== null && $pair['refresh_token'] !== '') {
                $credentials['refresh_token'] = $pair['refresh_token'];
            }
            if ($tenant !== null) {
                $this->persistZohoAccessTokenOnly($tenant, $pair['access_token'], $pair['refresh_token']);
            }
            $tokenHint = 'Latest access token (and refresh token if returned) were obtained from Zoho Accounts before any Zoho CRM API calls.';
        }

        return [$credentials, $tokenHint];
    }

    /**
     * @return array{
     *     access_token: string,
     *     refresh_token: string,
     *     client_id: string,
     *     client_secret: string,
     *     crm_api_base: string,
     *     authorization_code: string,
     *     redirect_uri: string,
     * }
     */
    public function credentialsFromTenantIntegration(array $integration): array
    {
        $values = isset($integration['values']) && is_array($integration['values'])
            ? $integration['values']
            : [];

        $accessToken = '';
        $refreshToken = '';
        $crmApiBase = '';
        $authorizationCode = '';
        $redirectUri = trim((string) config('services.zoho.oauth_redirect_uri', ''));
        $clientId = (string) config('services.zoho.oauth_client_id', '');
        $clientSecret = (string) config('services.zoho.oauth_client_secret', '');

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

                if ((str_contains($label, 'authorization') && str_contains($label, 'code'))
                    || (str_contains($label, 'oauth') && str_contains($label, 'code') && ! str_contains($label, 'token'))
                    || $label === 'code'
                    || (str_contains($label, 'grant') && str_contains($label, 'code'))) {
                    $authorizationCode = $val;
                } elseif (str_contains($label, 'redirect')) {
                    $redirectUri = $val;
                } elseif (str_contains($label, 'refresh')) {
                    $refreshToken = $val;
                } elseif (
                    (str_contains($label, 'access') && str_contains($label, 'token'))
                    || ($label === 'oauth access token')
                    || (str_contains($label, 'oauth') && str_contains($label, 'token') && ! str_contains($label, 'refresh') && ! str_contains($label, 'code'))
                ) {
                    $accessToken = $val;
                } elseif (str_contains($label, 'crm') && (str_contains($label, 'url') || str_contains($label, 'base') || str_contains($label, 'domain'))) {
                    $crmApiBase = $val;
                } elseif (str_contains($label, 'client') && str_contains($label, 'id') && ! str_contains($label, 'secret')) {
                    $clientId = $val;
                } elseif (str_contains($label, 'secret') || (str_contains($label, 'client') && str_contains($label, 'secret'))) {
                    $clientSecret = $val;
                }
            }
        }

        if ($authorizationCode === '') {
            foreach (['oauth_authorization_code_optional', 'authorization_code', 'oauth_code', 'zoho_authorization_code', 'code'] as $k) {
                if (! empty($values[$k]) && is_string($values[$k])) {
                    $authorizationCode = trim($values[$k]);
                    break;
                }
            }
        }

        if ($accessToken === '') {
            foreach (['oauth_access_token', 'access_token', 'zoho_access_token'] as $k) {
                if (! empty($values[$k]) && is_string($values[$k])) {
                    $accessToken = trim($values[$k]);
                    break;
                }
            }
        }

        if ($refreshToken === '') {
            foreach (['oauth_refresh_token', 'oauth_refresh_token_optional', 'refresh_token'] as $k) {
                if (! empty($values[$k]) && is_string($values[$k])) {
                    $refreshToken = trim($values[$k]);
                    break;
                }
            }
        }

        if ($crmApiBase === '') {
            foreach (['crm_api_base_url_optional', 'crm_api_base', 'zoho_crm_api_base'] as $k) {
                if (! empty($values[$k]) && is_string($values[$k])) {
                    $crmApiBase = rtrim(trim($values[$k]), '/');
                    break;
                }
            }
        }

        if ($crmApiBase === '') {
            $crmApiBase = rtrim((string) config('services.zoho.crm_api_base', 'https://www.zohoapis.in'), '/');
        } else {
            $crmApiBase = rtrim($crmApiBase, '/');
        }

        if ($redirectUri === '') {
            foreach (['oauth_redirect_uri_optional', 'redirect_uri', 'oauth_redirect_uri'] as $k) {
                if (! empty($values[$k]) && is_string($values[$k])) {
                    $redirectUri = trim($values[$k]);
                    break;
                }
            }
        }

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'client_id' => trim($clientId),
            'client_secret' => trim($clientSecret),
            'crm_api_base' => $crmApiBase,
            'authorization_code' => $authorizationCode,
            'redirect_uri' => trim($redirectUri),
        ];
    }

    /**
     * Public wrapper for OAuth callback / admin flows after the user returns from Zoho Accounts.
     *
     * @return array{access_token: string, refresh_token: string, api_domain: string}
     */
    public function exchangeAuthorizationCodeForTokens(
        string $code,
        string $clientId,
        string $clientSecret,
        string $redirectUri,
    ): array {
        return $this->exchangeAuthorizationCode($code, $clientId, $clientSecret, $redirectUri);
    }

    /**
     * @return array{access_token: string, refresh_token: string, api_domain: string}
     */
    private function exchangeAuthorizationCode(
        string $code,
        string $clientId,
        string $clientSecret,
        string $redirectUri,
    ): array {
        $response = $this->postZohoOAuthTokenRequest([
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'code' => trim($code),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatHttpErrorMessage($response, 'Zoho authorization code exchange failed'));
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Zoho token response was not valid JSON.');
        }

        $access = data_get($json, 'access_token');
        if (! is_string($access) || trim($access) === '') {
            $detail = $this->oauthErrorDetailFromJson($json);
            throw new RuntimeException(
                $detail !== null
                    ? 'Zoho token response did not include access_token: '.$detail
                    : 'Zoho did not return an access_token for this authorization code.',
            );
        }

        $refresh = data_get($json, 'refresh_token');
        $refreshStr = is_string($refresh) ? trim($refresh) : '';

        $apiDomain = '';
        $domainRaw = data_get($json, 'api_domain');
        if (is_string($domainRaw) && trim($domainRaw) !== '') {
            $apiDomain = trim($domainRaw);
            if (! str_starts_with($apiDomain, 'http://') && ! str_starts_with($apiDomain, 'https://')) {
                $apiDomain = 'https://'.$apiDomain;
            }
            $apiDomain = rtrim($apiDomain, '/');
        }

        return [
            'access_token' => trim($access),
            'refresh_token' => $refreshStr,
            'api_domain' => $apiDomain,
        ];
    }

    /**
     * Merge access/refresh tokens and API domain into the tenant integration field map.
     */
    public function persistTokensAfterAuthorizationCodeExchange(
        Tenant $tenant,
        string $accessToken,
        string $refreshToken,
        string $apiDomain,
    ): void {
        $this->persistZohoTokensAfterCodeExchange($tenant, $accessToken, $refreshToken, $apiDomain);
    }

    private function persistZohoTokensAfterCodeExchange(
        Tenant $tenant,
        string $accessToken,
        string $refreshToken,
        string $apiDomain,
    ): void {
        $integration = $tenant->integration;
        if (! is_array($integration) || (string) ($integration['slug'] ?? '') !== self::integrationSlug()) {
            return;
        }

        $values = isset($integration['values']) && is_array($integration['values'])
            ? $integration['values']
            : [];

        $model = Integration::query()->where('slug', self::integrationSlug())->first();
        if ($model === null) {
            return;
        }

        foreach ($model->fieldSpecs() as $spec) {
            $key = $spec['key'];
            $label = mb_strtolower($spec['label']);

            if ((str_contains($label, 'authorization') && str_contains($label, 'code'))
                || (str_contains($label, 'oauth') && str_contains($label, 'code') && ! str_contains($label, 'token'))
                || $label === 'code'
                || (str_contains($label, 'grant') && str_contains($label, 'code'))) {
                $values[$key] = '';

                continue;
            }

            if (str_contains($label, 'crm') && (str_contains($label, 'url') || str_contains($label, 'base') || str_contains($label, 'domain'))) {
                if ($apiDomain !== '') {
                    $values[$key] = $apiDomain;
                }

                continue;
            }

            if (str_contains($label, 'refresh')) {
                if ($refreshToken !== '') {
                    $values[$key] = $refreshToken;
                }

                continue;
            }

            if (
                (str_contains($label, 'access') && str_contains($label, 'token'))
                || ($label === 'oauth access token')
                || (str_contains($label, 'oauth') && str_contains($label, 'token') && ! str_contains($label, 'refresh') && ! str_contains($label, 'code'))
            ) {
                $values[$key] = $accessToken;
            }
        }

        $tenant->update([
            'integration' => [
                'slug' => self::integrationSlug(),
                'values' => $values,
            ],
        ]);
    }

    private function persistZohoAccessTokenOnly(Tenant $tenant, string $accessToken, ?string $newRefreshToken = null): void
    {
        $integration = $tenant->integration;
        if (! is_array($integration) || (string) ($integration['slug'] ?? '') !== self::integrationSlug()) {
            return;
        }

        $values = isset($integration['values']) && is_array($integration['values'])
            ? $integration['values']
            : [];

        $model = Integration::query()->where('slug', self::integrationSlug())->first();
        if ($model === null) {
            return;
        }

        foreach ($model->fieldSpecs() as $spec) {
            $key = $spec['key'];
            $label = mb_strtolower($spec['label']);

            if (str_contains($label, 'refresh')) {
                if ($newRefreshToken !== null && $newRefreshToken !== '') {
                    $values[$key] = $newRefreshToken;
                }

                continue;
            }

            if (
                (str_contains($label, 'access') && str_contains($label, 'token'))
                || ($label === 'oauth access token')
                || (str_contains($label, 'oauth') && str_contains($label, 'token') && ! str_contains($label, 'refresh') && ! str_contains($label, 'code'))
            ) {
                $values[$key] = $accessToken;
            }
        }

        $tenant->update([
            'integration' => [
                'slug' => self::integrationSlug(),
                'values' => $values,
            ],
        ]);
    }

    /**
     * @return list<CrmExternalUserResource>
     */
    private function fetchAllUsers(
        string $accessToken,
        string $crmApiBase,
        string $refreshToken,
        string $clientId,
        string $clientSecret,
    ): array {
        $version = trim((string) config('services.zoho.crm_api_version', 'v8'), '/');
        $token = trim($accessToken);
        $page = 1;
        $rows = [];
        $retriedWithRefresh = false;

        do {
            $response = $this->requestUsersPage($crmApiBase, $version, $token, $page);
     

            if ($response->status() === 401 && ! $retriedWithRefresh && $this->canRefresh($refreshToken, $clientId, $clientSecret)) {
                $token = $this->refreshAccessToken($refreshToken, $clientId, $clientSecret);
                $retriedWithRefresh = true;
                $response = $this->requestUsersPage($crmApiBase, $version, $token, $page);
            
            }

            if (! $response->successful()) {
                throw new RuntimeException($this->formatHttpErrorMessage($response));
            }

            $body = $response->json();
            if (! is_array($body)) {
                $body = [];
            }

            foreach ($this->iterUserRows($body) as $row) {
                $rows[] = $this->mapZohoUserToMysimconnect($row);
            }

            $more = (bool) data_get($body, 'info.more_records', false);
            $page++;
        } while ($more);

        return $rows;
    }

    private function requestUsersPage(string $crmApiBase, string $version, string $accessToken, int $page): Response
    {
        $url = "{$crmApiBase}/crm/{$version}/users";
        $query = [
            'type' => 'AllUsers',
            'page' => $page,
            'per_page' => 200,
        ];

      

        return Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken '.trim($accessToken),
            'Accept' => 'application/json',
        ])
            ->timeout(45)
            ->connectTimeout(10)
            ->get($url, $query);
    }

    private function canRefresh(string $refreshToken, string $clientId, string $clientSecret): bool
    {
        return $refreshToken !== '' && $clientId !== '' && $clientSecret !== '';
    }

    /**
     * @return array{access_token: string, refresh_token: ?string}
     */
    private function refreshAccessTokenPair(string $refreshToken, string $clientId, string $clientSecret): array
    {
        $response = $this->postZohoOAuthTokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatHttpErrorMessage($response, 'Zoho token refresh failed'));
        }

        $json = $response->json();
        $access = is_array($json) ? data_get($json, 'access_token') : null;

        if (! is_string($access) || trim($access) === '') {
            $detail = is_array($json) ? $this->oauthErrorDetailFromJson($json) : null;
            throw new RuntimeException(
                $detail !== null
                    ? 'Zoho token refresh did not return access_token: '.$detail
                    : 'Zoho token refresh did not return an access_token.',
            );
        }

        $newRefresh = null;
        if (is_array($json)) {
            $r = data_get($json, 'refresh_token');
            if (is_string($r) && trim($r) !== '') {
                $newRefresh = trim($r);
            }
        }

        return [
            'access_token' => trim($access),
            'refresh_token' => $newRefresh,
        ];
    }

    private function refreshAccessToken(string $refreshToken, string $clientId, string $clientSecret): string
    {
        return $this->refreshAccessTokenPair($refreshToken, $clientId, $clientSecret)['access_token'];
    }

    /**
     * Zoho expects token parameters as multipart form-data (same as Postman “form-data”).
     *
     * @param  array<string, string>  $fields
     */
    private function postZohoOAuthTokenRequest(array $fields): Response
    {
        $url = rtrim((string) config('services.zoho.accounts_token_url', 'https://accounts.zoho.in/oauth/v2/token'), '/');

        $multipart = [];
        foreach ($fields as $name => $contents) {
            $multipart[] = ['name' => $name, 'contents' => (string) $contents];
        }

        $response = Http::asMultipart()
            ->acceptJson()
            ->timeout(45)
            ->connectTimeout(10)
            ->post($url, $multipart);

        return $response;
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function oauthErrorDetailFromJson(array $json): ?string
    {
        $err = data_get($json, 'error');
        $desc = data_get($json, 'error_description');

        if (is_string($err) && $err !== '') {
            return is_string($desc) && $desc !== '' ? $err.' — '.$desc : $err;
        }

        return null;
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    private function iterUserRows(array $body): \Generator
    {
        $rows = data_get($body, 'users');

        if (! is_array($rows)) {
            return;
        }

        foreach ($rows as $row) {
            if (is_array($row)) {
                yield $row;
            }
        }
    }

    private function mapZohoUserToMysimconnect(array $row): CrmExternalUserResource
    {
        $full = trim((string) data_get($row, 'full_name', ''));
        if ($full === '') {
            $full = trim(trim((string) data_get($row, 'first_name', '')).' '.trim((string) data_get($row, 'last_name', '')));
        }

        $role = '';
        $roleNode = data_get($row, 'role');
        if (is_array($roleNode)) {
            $role = trim((string) data_get($roleNode, 'name', ''));
        }

        $phone = trim((string) (data_get($row, 'phone') ?: data_get($row, 'mobile') ?: ''));

        return new CrmExternalUserResource(
            id: (string) data_get($row, 'id', ''),
            name: $full !== '' ? $full : '(no name)',
            email: trim((string) data_get($row, 'email', '')),
            phone: $phone,
            role: $role,
        );
    }

    private function formatHttpErrorMessage(Response $response, ?string $fallback = null): string
    {
        $json = $response->json();

        if (is_array($json)) {
            $msg = data_get($json, 'message') ?? data_get($json, 'error_description') ?? data_get($json, 'error');

            if (is_string($msg) && $msg !== '') {
                return $msg;
            }
        }

        if ($fallback !== null) {
            return $fallback.' (HTTP '.$response->status().').';
        }

        return 'Zoho CRM API request failed (HTTP '.$response->status().').';
    }
}
