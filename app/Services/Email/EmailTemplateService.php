<?php

namespace App\Services\Email;

use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Cache;

final class EmailTemplateService
{
    private const CACHE_TTL_SECONDS = 3600;

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, html: string}
     */
    public static function render(string $slug, array $variables): array
    {
        $template = self::resolve($slug);

        if ($template === null || ! $template->is_active) {
            return self::fallback($slug, $variables);
        }

        return EmailTemplateRenderer::render(
            $template->subject,
            $template->html_body,
            $variables,
        );
    }

    public static function forget(string $slug): void
    {
        Cache::forget(self::cacheKey($slug));
    }

    public static function resolve(string $slug): ?EmailTemplate
    {
        return Cache::remember(
            self::cacheKey($slug),
            self::CACHE_TTL_SECONDS,
            fn (): ?EmailTemplate => EmailTemplate::query()->where('slug', $slug)->first(),
        );
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, html: string}
     */
    private static function fallback(string $slug, array $variables): array
    {
        return match ($slug) {
            EmailTemplate::SLUG_LOGIN_OTP => EmailTemplateRenderer::render(
                'Your login code',
                (string) view('mail.login-otp', ['otpCode' => $variables['otp_code'] ?? ''])->render(),
                $variables,
            ),
            EmailTemplate::SLUG_PASSWORD_RECOVERY => EmailTemplateRenderer::render(
                'Reset your password',
                '<p>Hello {{user_name}},</p><p><a href="{{reset_url}}">Reset password</a></p>',
                $variables,
            ),
            default => EmailTemplateRenderer::render('', '', $variables),
        };
    }

    private static function cacheKey(string $slug): string
    {
        return "email_template.{$slug}";
    }
}
