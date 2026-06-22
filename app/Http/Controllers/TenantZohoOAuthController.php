<?php

namespace App\Http\Controllers;

use App\Integrations\Connectors\Zoho\ZohoCrmUsersConnector;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Tenant-scoped browser OAuth with Zoho Accounts; token exchange stays server-side.
 */
class TenantZohoOAuthController extends Controller
{
    private const SESSION_KEY = 'zoho_oauth_tenant';

    public function start(Request $request, Tenant $tenant, ZohoCrmUsersConnector $zoho): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $tenant->refresh();
        $integration = $tenant->integration;
        if (! is_array($integration) || (($integration['slug'] ?? '')) !== ZohoCrmUsersConnector::integrationSlug()) {
            return redirect()->route('admin.tenants.edit', $tenant)
                ->withErrors([
                    'integration.slug' => 'Select Zoho, save tenant credentials first, then try again.',
                ]);
        }

        $creds = $zoho->credentialsFromTenantIntegration($integration);

        if ($creds['client_id'] === '' || $creds['client_secret'] === '') {
            return redirect()->route('admin.tenants.edit', $tenant)
                ->withErrors([
                    'integration.values' => 'Zoho OAuth requires a client ID and client secret on this tenant (or set ZOHO_OAUTH_CLIENT_* in .env). Save them before connecting.',
                ]);
        }

        $callbackUrl = route('admin.tenants.integrations.zoho.oauth.callback', ['tenant' => $tenant], true);

        $state = Str::random(48);

        $request->session()->put(self::SESSION_KEY, [
            'state' => $state,
            'tenant_id' => $tenant->id,
            'user_id' => (int) $request->user()->id,
            'callback_url' => $callbackUrl,
        ]);

        $authorizeBase = rtrim((string) config('services.zoho.accounts_authorize_url', 'https://accounts.zoho.in/oauth/v2/auth'), '?&');
        $scope = trim((string) config(
            'services.zoho.oauth_scopes',
            'ZohoCRM.users.READ,ZohoCRM.modules.ALL,ZohoCRM.settings.tags.READ'
        ));

        $query = http_build_query([
            'scope' => $scope,
            'client_id' => $creds['client_id'],
            'response_type' => 'code',
            'access_type' => 'offline',
            'redirect_uri' => $callbackUrl,
            'state' => $state,
            'prompt' => 'consent',
        ]);

        return redirect()->away($authorizeBase.'?'.$query);
    }

    public function callback(Request $request, Tenant $tenant, ZohoCrmUsersConnector $zoho): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $stored = $request->session()->pull(self::SESSION_KEY);

        if (! is_array($stored)) {
            abort(403, 'OAuth session expired. Start authorization again from the tenant integration tab.');
        }

        if (($stored['tenant_id'] ?? null) !== $tenant->id
            || (int) ($stored['user_id'] ?? 0) !== (int) $request->user()->id) {
            abort(403, 'Invalid OAuth session for this tenant.');
        }

        if ($request->filled('error')) {
            $desc = $request->query('error_description');
            $err = $request->query('error');
            $hint = is_string($desc) && trim($desc) !== ''
                ? trim($desc)
                : (is_string($err) && trim($err) !== '' ? trim($err) : 'Zoho OAuth was denied.');

            return redirect()->route('admin.tenants.edit', $tenant)->withErrors([
                'integration.oauth' => $hint,
            ]);
        }

        $stateQuery = $request->query('state');
        $stateStored = $stored['state'] ?? '';
        if (! is_string($stateQuery) || $stateStored === '' || ! hash_equals($stateStored, $stateQuery)) {
            abort(403, 'Invalid OAuth state.');
        }

        $code = $request->query('code');
        if (! is_string($code) || trim($code) === '') {
            return redirect()->route('admin.tenants.edit', $tenant)
                ->withErrors(['integration.oauth' => 'Zoho did not return an authorization code.']);
        }

        $callbackUrl = is_string($stored['callback_url'] ?? null)
            ? $stored['callback_url']
            : route('admin.tenants.integrations.zoho.oauth.callback', ['tenant' => $tenant], true);

        $tenant->refresh();
        $integration = $tenant->integration;
        if (! is_array($integration) || (($integration['slug'] ?? '')) !== ZohoCrmUsersConnector::integrationSlug()) {
            return redirect()->route('admin.tenants.edit', $tenant)
                ->withErrors(['integration.oauth' => 'Tenant integration must be Zoho to complete OAuth.']);
        }

        $creds = $zoho->credentialsFromTenantIntegration($integration);

        if ($creds['client_id'] === '' || $creds['client_secret'] === '') {
            return redirect()->route('admin.tenants.edit', $tenant)
                ->withErrors([
                    'integration.oauth' => 'Missing Zoho client id or secret when exchanging the authorization code.',
                ]);
        }

        try {
            $tokens = $zoho->exchangeAuthorizationCodeForTokens(
                trim($code),
                $creds['client_id'],
                $creds['client_secret'],
                $callbackUrl,
            );
        } catch (RuntimeException $e) {
            return redirect()->route('admin.tenants.edit', $tenant)
                ->withErrors(['integration.oauth' => $e->getMessage()]);
        }

        $zoho->persistTokensAfterAuthorizationCodeExchange(
            $tenant,
            $tokens['access_token'],
            $tokens['refresh_token'],
            $tokens['api_domain'],
        );

        return redirect()->route('admin.tenants.edit', $tenant)
            ->with('success', 'Zoho OAuth completed — access token, refresh token, and CRM API URL were saved on this tenant.');
    }
}
