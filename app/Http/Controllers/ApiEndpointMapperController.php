<?php

namespace App\Http\Controllers;

use App\Models\ApiEndpointMapping;
use App\Models\Integration;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ApiEndpointMapperController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/ApiEndpointMapper/Index', [
            'crm' => [
                'integrations' => $this->integrationOptions(),
            ],
            'savedMappings' => $this->savedMappings(),
        ]);
    }

    public function mappedApis()
    {
        return Inertia::render('Admin/MappedApis/Index', [
            'integrations' => Integration::query()
                ->orderBy('type')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'type', 'documentation', 'enabled'])
                ->map(fn (Integration $integration) => [
                    'id' => $integration->id,
                    'name' => $integration->name,
                    'slug' => $integration->slug,
                    'type' => $integration->type,
                    'documentation' => $integration->documentation,
                    'enabled' => $integration->enabled,
                ]),
        ]);
    }

    public function systemEndpoints(Request $request)
    {
        // Admin-only surface area: keep the endpoint list tenant/auth protected by route middleware.
        // This mapper is for CRM integrations, so default to CRM-compat endpoints only.
        $crmPrefix = 'api/crm';

        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(function (IlluminateRoute $r) {
                $uri = ltrim((string) $r->uri(), '/');
                if ($uri === '') return false;

                // Hide internal / vendor debug routes.
                if (str_starts_with($uri, '_debugbar')) return false;
                if (str_starts_with($uri, 'telescope')) return false;
                if (str_starts_with($uri, 'horizon')) return false;

                return true;
            })
            ->filter(function (IlluminateRoute $r) use ($crmPrefix) {
                $uri = ltrim((string) $r->uri(), '/');
                return str_starts_with($uri, $crmPrefix);
            })
            ->map(function (IlluminateRoute $r) use ($request) {
                $methods = array_values(array_diff($r->methods(), ['HEAD']));
                $method = $methods[0] ?? 'GET';

                $action = $r->getActionName();
                $name = $r->getName();
                $uri = '/' . ltrim((string) $r->uri(), '/');

                $description = $name
                    ? ('Route: ' . $name)
                    : (str_contains($action, '@') ? $action : 'Internal endpoint');

                return [
                    'id' => md5($method . ' ' . $uri . ' ' . ($name ?? '')),
                    'method' => $method,
                    'uri' => $uri,
                    'url' => $request->getSchemeAndHttpHost() . $uri,
                    'name' => $name,
                    'action' => $action,
                    'description' => $description,
                    'middleware' => array_values((array) $r->gatherMiddleware()),
                ];
            })
            ->sortBy([
                ['uri', 'asc'],
                ['method', 'asc'],
            ])
            ->values();

        return response()->json([
            'data' => $routes,
        ]);
    }

    public function storeMapping(Request $request)
    {
        if (! Schema::hasTable('api_endpoint_mappings')) {
            return response()->json([
                'message' => 'API endpoint mappings table is not available. Run the latest migration before saving mappings.',
            ], 503);
        }

        $data = $request->validate([
            'integration_slug' => ['required', 'string', Rule::exists('integrations', 'slug')],
            'system_endpoint_id' => ['required', 'string', 'max:64'],
            'system_method' => ['required', 'string', 'max:12'],
            'system_uri' => ['required', 'string', 'max:255'],
            'system_name' => ['nullable', 'string', 'max:255'],
            'crm_endpoint_key' => ['required', 'string', 'max:150'],
            'crm_method' => ['required', 'string', 'max:12'],
            'crm_uri' => ['required', 'string', 'max:255'],
            'crm_name' => ['nullable', 'string', 'max:255'],
            'field_mappings' => ['array'],
            'field_mappings.*.source' => ['required', 'string', 'max:255'],
            'field_mappings.*.dest' => ['required', 'string', 'max:255'],
            'field_mappings.*.transform' => ['nullable', 'string', 'max:255'],
            'field_mappings.*.required' => ['boolean'],
        ]);

        $integration = Integration::query()
            ->where('slug', $data['integration_slug'])
            ->firstOrFail();

        $mapping = ApiEndpointMapping::query()->updateOrCreate(
            [
                'integration_slug' => $data['integration_slug'],
                'system_method' => strtoupper($data['system_method']),
                'system_uri' => $data['system_uri'],
                'crm_endpoint_key' => $data['crm_endpoint_key'],
            ],
            [
                'integration_id' => $integration->id,
                'system_endpoint_id' => $data['system_endpoint_id'],
                'system_name' => $data['system_name'] ?? null,
                'crm_method' => strtoupper($data['crm_method']),
                'crm_uri' => $data['crm_uri'],
                'crm_name' => $data['crm_name'] ?? null,
                'field_mappings' => $data['field_mappings'] ?? [],
                'enabled' => true,
            ]
        );

        return response()->json([
            'message' => 'API endpoint mapping saved.',
            'data' => $this->mappingPayload($mapping->refresh()),
        ]);
    }

    private function integrationOptions()
    {
        return Integration::query()
            ->where('enabled', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'type', 'documentation', 'enabled'])
            ->map(fn (Integration $integration) => [
                'id' => $integration->id,
                'name' => $integration->name,
                'slug' => $integration->slug,
                'type' => $integration->type,
                'documentation' => $integration->documentation,
                'enabled' => $integration->enabled,
            ])
            ->values();
    }

    private function savedMappings()
    {
        if (! Schema::hasTable('api_endpoint_mappings')) {
            return collect();
        }

        return ApiEndpointMapping::query()
            ->latest()
            ->get()
            ->map(fn (ApiEndpointMapping $mapping) => $this->mappingPayload($mapping));
    }

    private function mappingPayload(ApiEndpointMapping $mapping): array
    {
        return [
            'id' => $mapping->id,
            'integration_id' => $mapping->integration_id,
            'integration_slug' => $mapping->integration_slug,
            'system_endpoint_id' => $mapping->system_endpoint_id,
            'system_method' => $mapping->system_method,
            'system_uri' => $mapping->system_uri,
            'system_name' => $mapping->system_name,
            'crm_endpoint_key' => $mapping->crm_endpoint_key,
            'crm_method' => $mapping->crm_method,
            'crm_uri' => $mapping->crm_uri,
            'crm_name' => $mapping->crm_name,
            'field_mappings' => $mapping->field_mappings ?? [],
            'enabled' => $mapping->enabled,
            'updated_at' => optional($mapping->updated_at)->toIso8601String(),
        ];
    }
}

