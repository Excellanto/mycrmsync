<?php

namespace App\Services\Integrations;

use App\Models\TenantSetting;

class OpenAiConfigService
{
    public const DEFAULT_GPT_MODEL = 'gpt-4';

    public const DEFAULT_WHISPER_MODEL = 'whisper-1';

    public const SYSTEM_KEY_API_KEY = 'openai.api_key';

    public const SYSTEM_KEY_GPT_MODEL = 'openai.gpt_model';

    public const SYSTEM_KEY_WHISPER_MODEL = 'openai.whisper_model';

    /** @var list<string> */
    public const GPT_MODELS = ['gpt-4', 'gpt-4o', 'gpt-4o-mini'];

    public function __construct(private readonly int $tenantId) {}

    public static function forTenant(int $tenantId): self
    {
        return new self($tenantId);
    }

    public function apiKey(): ?string
    {
        $tenantKey = $this->tenantApiKey();

        if ($tenantKey !== null) {
            return $tenantKey;
        }

        return self::systemApiKey();
    }

    public function gptModel(): string
    {
        if (TenantSetting::hasValue($this->tenantId, TenantSetting::KEY_OPENAI_GPT_MODEL)) {
            $model = TenantSetting::getValue($this->tenantId, TenantSetting::KEY_OPENAI_GPT_MODEL);

            return in_array($model, self::GPT_MODELS, true) ? $model : self::DEFAULT_GPT_MODEL;
        }

        return self::systemGptModel();
    }

    public function whisperModel(): string
    {
        if (TenantSetting::hasValue($this->tenantId, TenantSetting::KEY_OPENAI_WHISPER_MODEL)) {
            $model = TenantSetting::getValue($this->tenantId, TenantSetting::KEY_OPENAI_WHISPER_MODEL);

            return is_string($model) && $model !== '' ? $model : self::DEFAULT_WHISPER_MODEL;
        }

        return self::systemWhisperModel();
    }

    public function hasTenantApiKey(): bool
    {
        return TenantSetting::hasValue($this->tenantId, TenantSetting::KEY_OPENAI_API_KEY);
    }

    public function hasTenantGptModel(): bool
    {
        return TenantSetting::hasValue($this->tenantId, TenantSetting::KEY_OPENAI_GPT_MODEL);
    }

    public static function systemHasApiKey(): bool
    {
        return self::systemApiKey() !== null;
    }

    public function usesSystemApiKeyFallback(): bool
    {
        return ! $this->hasTenantApiKey() && self::systemHasApiKey();
    }

    public function usesSystemGptModelFallback(): bool
    {
        return ! $this->hasTenantGptModel();
    }

    public function isConfigured(): bool
    {
        return $this->apiKey() !== null;
    }

    public function tenantGptModelForForm(): string
    {
        if ($this->hasTenantGptModel()) {
            $model = TenantSetting::getValue($this->tenantId, TenantSetting::KEY_OPENAI_GPT_MODEL);

            return in_array($model, self::GPT_MODELS, true) ? $model : self::DEFAULT_GPT_MODEL;
        }

        return self::systemGptModel();
    }

    public function tenantWhisperModelForForm(): string
    {
        if (TenantSetting::hasValue($this->tenantId, TenantSetting::KEY_OPENAI_WHISPER_MODEL)) {
            $model = TenantSetting::getValue($this->tenantId, TenantSetting::KEY_OPENAI_WHISPER_MODEL);

            return is_string($model) && $model !== '' ? $model : self::DEFAULT_WHISPER_MODEL;
        }

        return self::systemWhisperModel();
    }

    public static function systemApiKey(): ?string
    {
        $fromSettings = settings(self::SYSTEM_KEY_API_KEY);
        if (is_string($fromSettings) && trim($fromSettings) !== '') {
            return trim($fromSettings);
        }

        $fromEnv = config('services.openai.api_key');

        return is_string($fromEnv) && trim($fromEnv) !== '' ? trim($fromEnv) : null;
    }

    public static function systemGptModel(): string
    {
        $fromSettings = settings(self::SYSTEM_KEY_GPT_MODEL);
        if (is_string($fromSettings) && in_array($fromSettings, self::GPT_MODELS, true)) {
            return $fromSettings;
        }

        $fromEnv = config('services.openai.gpt_model', self::DEFAULT_GPT_MODEL);

        return in_array($fromEnv, self::GPT_MODELS, true) ? $fromEnv : self::DEFAULT_GPT_MODEL;
    }

    public static function systemWhisperModel(): string
    {
        $fromSettings = settings(self::SYSTEM_KEY_WHISPER_MODEL);
        if (is_string($fromSettings) && $fromSettings !== '') {
            return $fromSettings;
        }

        $fromEnv = config('services.openai.whisper_model', self::DEFAULT_WHISPER_MODEL);

        return is_string($fromEnv) && $fromEnv !== '' ? $fromEnv : self::DEFAULT_WHISPER_MODEL;
    }

    private function tenantApiKey(): ?string
    {
        $key = TenantSetting::getValue($this->tenantId, TenantSetting::KEY_OPENAI_API_KEY);

        return is_string($key) && $key !== '' ? $key : null;
    }
}
