<?php

namespace App\Http\Middleware;

use App\Support\ApplicationCache;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => $this->authBundle($request)['user'],
                'permissions' => fn () => $this->authBundle($request)['permissions'],
                'contact_management_available' => fn () => $this->authBundle($request)['contact_management_available'],
                'can' => fn () => $this->authBundle($request)['can'],
            ],
            'crm' => [
                'enabled' => fn () => $request->user()
                    ? array_map(
                        fn (array $integration) => $integration['name'],
                        ApplicationCache::rememberEnabledIntegrations(),
                    )
                    : [],
                'integrations' => fn () => $request->user()
                    ? ApplicationCache::rememberEnabledIntegrations()
                    : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    /**
     * @return array{
     *     user: array<string, mixed>|null,
     *     permissions: list<string>,
     *     can: array<string, mixed>,
     *     contact_management_available: bool
     * }
     */
    private function authBundle(Request $request): array
    {
        if ($request->attributes->has('inertia_auth_bundle')) {
            /** @var array{user: array<string, mixed>|null, permissions: list<string>, can: array<string, mixed>, contact_management_available: bool} $bundle */
            $bundle = $request->attributes->get('inertia_auth_bundle');

            return $bundle;
        }

        $user = $request->user();
        $bundle = $user
            ? ApplicationCache::rememberUserAuth($user)
            : ApplicationCache::emptyAuthBundle();

        $request->attributes->set('inertia_auth_bundle', $bundle);

        return $bundle;
    }
}
