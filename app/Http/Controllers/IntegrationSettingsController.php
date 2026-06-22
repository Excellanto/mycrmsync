<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Services\Integrations\OpenAiConfigService;
use App\Services\Integrations\StorageConfigService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class IntegrationSettingsController extends Controller
{
    private const MASKED_SECRET_PLACEHOLDER = '••••••••';

    public function index(Request $request)
    {
        $this->authorize('viewAny', TenantSetting::class);

        $user = $request->user();
        $tenantId = $this->resolveTenantId($user, $request);
        $isMaster = $user->isMaster();

        return Inertia::render('Admin/Integrations/Index', [
            'openai' => $tenantId ? $this->openAiFormPayload($tenantId) : null,
            'storage' => $tenantId ? StorageConfigService::forTenant($tenantId)->formPayload() : null,
            'gptModels' => OpenAiConfigService::GPT_MODELS,
            'isMaster' => $isMaster,
            'tenants' => $isMaster ? $this->tenantOptions() : [],
            'selectedTenantId' => $tenantId,
        ]);
    }

    public function updateOpenAi(Request $request)
    {
        $this->authorize('update', new TenantSetting());

        $user = $request->user();
        $tenantId = $this->resolveTenantId($user, $request, required: true);

        $data = $request->validate([
            'openai_api_key' => ['nullable', 'string', 'max:500'],
            'gpt_model' => ['required', 'string', 'in:'.implode(',', OpenAiConfigService::GPT_MODELS)],
            'tenant_id' => $this->tenantIdRules($user),
        ]);

        $apiKey = trim((string) ($data['openai_api_key'] ?? ''));

        if ($apiKey !== '' && $apiKey !== self::MASKED_SECRET_PLACEHOLDER) {
            TenantSetting::setValue(
                $tenantId,
                TenantSetting::KEY_OPENAI_API_KEY,
                $apiKey,
                TenantSetting::TYPE_SECRET
            );
        }

        TenantSetting::setValue(
            $tenantId,
            TenantSetting::KEY_OPENAI_GPT_MODEL,
            $data['gpt_model']
        );

        TenantSetting::setValue(
            $tenantId,
            TenantSetting::KEY_OPENAI_WHISPER_MODEL,
            OpenAiConfigService::DEFAULT_WHISPER_MODEL
        );

        return back()->with('success', 'OpenAI settings saved.');
    }

    public function updateStorage(Request $request, string $provider)
    {
        $this->authorize('update', new TenantSetting());

        if (! in_array($provider, StorageConfigService::PROVIDERS, true)) {
            abort(404);
        }

        $user = $request->user();
        $tenantId = $this->resolveTenantId($user, $request, required: true);

        match ($provider) {
            StorageConfigService::PROVIDER_SUPABASE => $this->saveSupabaseStorage($request, $tenantId),
            StorageConfigService::PROVIDER_GOOGLE_DRIVE => $this->saveGoogleDriveStorage($request, $tenantId),
            StorageConfigService::PROVIDER_DROPBOX => $this->saveDropboxStorage($request, $tenantId),
            StorageConfigService::PROVIDER_R2 => $this->saveR2Storage($request, $tenantId),
        };

        $label = match ($provider) {
            StorageConfigService::PROVIDER_R2 => 'Cloudflare R2',
            StorageConfigService::PROVIDER_GOOGLE_DRIVE => 'Google Drive',
            default => ucfirst(str_replace('_', ' ', $provider)),
        };

        return back()->with('success', $label.' storage settings saved.');
    }

    private function saveSupabaseStorage(Request $request, int $tenantId): void
    {
        $data = $request->validate([
            'url' => ['nullable', 'string', 'max:500', 'url'],
            'key' => ['nullable', 'string', 'max:1000'],
            'bucket' => ['nullable', 'string', 'max:255'],
            'is_default' => ['boolean'],
            'tenant_id' => $this->tenantIdRules($request->user()),
        ]);

        if (trim((string) ($data['url'] ?? '')) !== '') {
            TenantSetting::setValue($tenantId, TenantSetting::KEY_STORAGE_SUPABASE_URL, trim($data['url']));
        }

        $this->persistSecretIfProvided(
            $tenantId,
            TenantSetting::KEY_STORAGE_SUPABASE_KEY,
            $data['key'] ?? null
        );

        if (trim((string) ($data['bucket'] ?? '')) !== '') {
            TenantSetting::setValue($tenantId, TenantSetting::KEY_STORAGE_SUPABASE_BUCKET, trim($data['bucket']));
        }

        $config = StorageConfigService::forTenant($tenantId);
        $this->applyDefaultProvider($tenantId, StorageConfigService::PROVIDER_SUPABASE, (bool) ($data['is_default'] ?? false), $config);
    }

    private function saveGoogleDriveStorage(Request $request, int $tenantId): void
    {
        $data = $request->validate([
            'client_id' => ['nullable', 'string', 'max:500'],
            'client_secret' => ['nullable', 'string', 'max:1000'],
            'folder_id' => ['nullable', 'string', 'max:500'],
            'is_default' => ['boolean'],
            'tenant_id' => $this->tenantIdRules($request->user()),
        ]);

        if (trim((string) ($data['client_id'] ?? '')) !== '') {
            TenantSetting::setValue($tenantId, TenantSetting::KEY_STORAGE_GOOGLE_DRIVE_CLIENT_ID, trim($data['client_id']));
        }

        $this->persistSecretIfProvided(
            $tenantId,
            TenantSetting::KEY_STORAGE_GOOGLE_DRIVE_CLIENT_SECRET,
            $data['client_secret'] ?? null
        );

        TenantSetting::setValue(
            $tenantId,
            TenantSetting::KEY_STORAGE_GOOGLE_DRIVE_FOLDER_ID,
            trim((string) ($data['folder_id'] ?? ''))
        );

        $config = StorageConfigService::forTenant($tenantId);
        $this->applyDefaultProvider($tenantId, StorageConfigService::PROVIDER_GOOGLE_DRIVE, (bool) ($data['is_default'] ?? false), $config);
    }

    private function saveDropboxStorage(Request $request, int $tenantId): void
    {
        $data = $request->validate([
            'app_key' => ['nullable', 'string', 'max:500'],
            'app_secret' => ['nullable', 'string', 'max:1000'],
            'refresh_token' => ['nullable', 'string', 'max:2000'],
            'is_default' => ['boolean'],
            'tenant_id' => $this->tenantIdRules($request->user()),
        ]);

        if (trim((string) ($data['app_key'] ?? '')) !== '') {
            TenantSetting::setValue($tenantId, TenantSetting::KEY_STORAGE_DROPBOX_APP_KEY, trim($data['app_key']));
        }

        $this->persistSecretIfProvided(
            $tenantId,
            TenantSetting::KEY_STORAGE_DROPBOX_APP_SECRET,
            $data['app_secret'] ?? null
        );

        $this->persistSecretIfProvided(
            $tenantId,
            TenantSetting::KEY_STORAGE_DROPBOX_REFRESH_TOKEN,
            $data['refresh_token'] ?? null
        );

        $config = StorageConfigService::forTenant($tenantId);
        $this->applyDefaultProvider($tenantId, StorageConfigService::PROVIDER_DROPBOX, (bool) ($data['is_default'] ?? false), $config);
    }

    private function saveR2Storage(Request $request, int $tenantId): void
    {
        $existing = StorageConfigService::forTenant($tenantId);

        $data = $request->validate([
            'public_url' => [
                Rule::requiredIf(
                    ! $existing->hasTenantR2PublicUrl() && ! StorageConfigService::systemHasR2PublicUrl()
                ),
                'nullable',
                'string',
                'max:500',
                'url',
            ],
            'is_default' => ['boolean'],
            'tenant_id' => $this->tenantIdRules($request->user()),
        ]);

        if (trim((string) ($data['public_url'] ?? '')) !== '') {
            TenantSetting::setValue(
                $tenantId,
                TenantSetting::KEY_STORAGE_R2_PUBLIC_URL,
                rtrim(trim($data['public_url']), '/')
            );
        }

        $config = StorageConfigService::forTenant($tenantId);
        $this->applyDefaultProvider($tenantId, StorageConfigService::PROVIDER_R2, (bool) ($data['is_default'] ?? false), $config);
    }

    private function applyDefaultProvider(
        int $tenantId,
        string $provider,
        bool $isDefault,
        StorageConfigService $config
    ): void {
        if ($isDefault) {
            if (! $config->isProviderConfigured($provider)) {
                throw ValidationException::withMessages([
                    'is_default' => ['Configure this provider before setting it as default.'],
                ]);
            }

            TenantSetting::setValue($tenantId, TenantSetting::KEY_STORAGE_DEFAULT_PROVIDER, $provider);

            return;
        }

        if ($config->isProviderDefault($provider)) {
            TenantSetting::setValue($tenantId, TenantSetting::KEY_STORAGE_DEFAULT_PROVIDER, '');
        }
    }

    private function persistSecretIfProvided(int $tenantId, string $key, mixed $value): void
    {
        $secret = trim((string) ($value ?? ''));

        if ($secret === '' || $secret === self::MASKED_SECRET_PLACEHOLDER) {
            return;
        }

        TenantSetting::setValue($tenantId, $key, $secret, TenantSetting::TYPE_SECRET);
    }

    /**
     * @return array<int, mixed>
     */
    private function tenantIdRules(User $user): array
    {
        return [
            Rule::requiredIf($user->isMaster()),
            'nullable',
            'integer',
            'exists:tenants,id',
        ];
    }

    private function resolveTenantId(User $user, Request $request, bool $required = false): ?int
    {
        if ($user->isMaster()) {
            $tenantId = $request->input('tenant_id') ?? $request->query('tenant_id');

            if ($tenantId === null || $tenantId === '') {
                if ($required) {
                    abort(422, 'Select a tenant to save integration settings.');
                }

                return null;
            }

            return (int) $tenantId;
        }

        if ($user->tenant_id === null) {
            abort(403, 'Integration settings require a tenant account.');
        }

        return (int) $user->tenant_id;
    }

    /**
     * @return list<array{id: int, company_name: string}>
     */
    private function tenantOptions(): array
    {
        return Tenant::query()
            ->orderBy('company_name')
            ->get(['id', 'company_name'])
            ->map(fn (Tenant $tenant) => [
                'id' => $tenant->id,
                'company_name' => $tenant->company_name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     has_tenant_api_key: bool,
     *     has_tenant_gpt_model: bool,
     *     using_system_api_key: bool,
     *     system_has_api_key: bool,
     *     gpt_model: string,
     *     whisper_model: string,
     *     system_gpt_model: string,
     *     is_configured: bool
     * }
     */
    private function openAiFormPayload(int $tenantId): array
    {
        $config = OpenAiConfigService::forTenant($tenantId);

        return [
            'has_tenant_api_key' => $config->hasTenantApiKey(),
            'has_tenant_gpt_model' => $config->hasTenantGptModel(),
            'using_system_api_key' => $config->usesSystemApiKeyFallback(),
            'system_has_api_key' => OpenAiConfigService::systemHasApiKey(),
            'gpt_model' => $config->tenantGptModelForForm(),
            'whisper_model' => $config->tenantWhisperModelForForm(),
            'system_gpt_model' => OpenAiConfigService::systemGptModel(),
            'is_configured' => $config->isConfigured(),
        ];
    }
}
