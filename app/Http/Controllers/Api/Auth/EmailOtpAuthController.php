<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Integrations\FetchTenantIntegrationCrmUsers;
use App\Mail\LoginOtpMail;
use App\Models\Integration;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CallLog\CallLogSyncTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * @group Auth (email OTP)
 *
 * Passwordless login via email one-time code for tenant users.
 */
final class EmailOtpAuthController extends Controller
{
    public function __construct(
        private FetchTenantIntegrationCrmUsers $fetchTenantIntegrationCrmUsers,
        private CallLogSyncTokenService $callLogSyncTokenService,
    ) {}

    private const OTP_TTL_SECONDS = 600;

    private const MAX_VERIFY_FAILS = 8;

    /**
     * Send login OTP to email
     *
     * @unauthenticated
     *
     * @bodyParam email string required The user's email. Example: user@example.com
     */
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $emailKey = $this->normalizedEmailKey($data['email']);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user) {
            return ApiResponse::error('No account found for this email address.', 404);
        }

        $otp = $this->issueOtp();
        $digest = hash_hmac('sha256', $otp, (string) config('app.key'));

        Cache::put('email_otp_digest:'.$emailKey, $digest, now()->addSeconds(self::OTP_TTL_SECONDS));
        Cache::forget('email_otp_verify_fails:'.$emailKey);

        try {
            Mail::to($user->email)->send(new LoginOtpMail($otp));
        } catch (Throwable $e) {
            Cache::forget('email_otp_digest:'.$emailKey);

            report($e);

            return ApiResponse::error('We could not send the login code. Check mail configuration or try again later.', 500);
        }

        return ApiResponse::success(
            null,
            'A 4-digit code has been sent to your email. Use it within 10 minutes.'
        );
    }

    /**
     * Verify email OTP and return user context, Sanctum token, and integrated system metadata
     *
     * @unauthenticated
     *
     * @bodyParam email string required The user's email.
     * @bodyParam otp string required Four-digit code. Example: 1234
     */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'otp' => ['required', 'regex:/^[0-9]{4}$/'],
        ]);

        $emailKey = $this->normalizedEmailKey($data['email']);

        $failsKey = 'email_otp_verify_fails:'.$emailKey;
        $fails = (int) Cache::get($failsKey, 0);
        if ($fails >= self::MAX_VERIFY_FAILS) {
            return ApiResponse::error('Too many incorrect attempts. Request a new code.', 429);
        }

        $user = User::query()
            ->with(['tenant', 'roles'])
            ->where('email', $data['email'])
            ->first();

        if (! $user) {
            return ApiResponse::error('No account found for this email address.', 404);
        }

        $stored = Cache::get('email_otp_digest:'.$emailKey);
        if ($stored === null) {
            return ApiResponse::error('This code has expired or was already used. Request a new code.', 400);
        }

        $candidate = hash_hmac('sha256', $data['otp'], (string) config('app.key'));
        if (! hash_equals($stored, $candidate)) {
            Cache::put($failsKey, $fails + 1, now()->addMinutes(30));

            return ApiResponse::error('Invalid login code.', 400);
        }

        Cache::forget('email_otp_digest:'.$emailKey);
        Cache::forget($failsKey);

        $user->forceFill(['last_login_at' => now()])->save();

        $tenant = $user->tenant;

        $user->tokens()->where('name', 'mobile')->delete();

        $tokenExpiresAt = now()->addDays((int) config('auth.sanctum_mobile.token_expiration_days', 30));
        $accessToken = $user->createToken('mobile', ['*'], $tokenExpiresAt);
        $callSyncToken = $this->callLogSyncTokenService->issue($user);

        return ApiResponse::success(
            [
                'access_token' => $accessToken->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $tokenExpiresAt->toIso8601String(),
                'call_sync_token' => $callSyncToken,
                'user' => $this->otpSuccessUserPayload($user, $tenant),
                'Integrated_system' => $this->integratedSystemPayload($tenant),
            ],
            'Login verified successfully.'
        );
    }

    /**
     * Logout (revoke current Bearer token)
     *
     * @authenticated
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        $user->currentAccessToken()?->delete();

        return ApiResponse::success(null, 'Logged out successfully.');
    }

    private function otpSuccessUserPayload(User $user, ?Tenant $tenant): array
    {
        $payload = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'tenant_id' => $user->tenant_id,
            'intsysuser' => $user->intsysuser,
            'userrole' => (string) ($user->roles->first()?->slug ?? ''),
        ];

        $payload['integrated_user'] = $this->resolvedIntegratedUserProfile($user, $tenant);

        return $payload;
    }

    /**
     * CRM / integrated identity row matching this user's intsysuser (if any).
     *
     * @return array{id: string, name: string, email: string, phone: string, role: string}|null
     */
    private function resolvedIntegratedUserProfile(User $user, ?Tenant $tenant): ?array
    {
        if ($tenant === null) {
            return null;
        }

        $externalId = $user->intsysuser;
        if ($externalId === null || $externalId === '') {
            return null;
        }

        $externalId = (string) $externalId;

        foreach ($this->fetchTenantIntegrationCrmUsers->mappedUsersOrEmpty($tenant) as $row) {
            if ((string) ($row['id'] ?? '') === $externalId) {
                return [
                    'id' => (string) $row['id'],
                    'name' => (string) ($row['name'] ?? ''),
                    'email' => (string) ($row['email'] ?? ''),
                    'phone' => (string) ($row['phone'] ?? ''),
                    'role' => (string) ($row['role'] ?? ''),
                ];
            }
        }

        return null;
    }

    /**
     * Integration the tenant uses (public metadata only; no credential values).
     *
     * @return array<string, mixed>|null
     */
    private function integratedSystemPayload(?Tenant $tenant): ?array
    {
        if ($tenant === null) {
            return null;
        }

        $slug = data_get($tenant->integration, 'slug');
        if ($slug === null || $slug === '') {
            return null;
        }

        $slug = (string) $slug;
        $model = Integration::query()->where('slug', $slug)->first();

        return [
            'slug' => $slug,
            'name' => $model?->name,
            'type' => $model?->type,
            'documentation' => $model?->documentation,
            'integration_status' => (bool) $tenant->integration_status,
        ];
    }

    private function normalizedEmailKey(string $email): string
    {
        return hash('sha256', Str::lower(Str::squish($email)));
    }

    /**
     * Four-digit OTP for the outgoing mail. Uses EMAIL_OTP_STATIC when set;
     * otherwise non-production falls back to config auth.email_otp.static_dev_fallback (2468).
     */
    private function issueOtp(): string
    {
        $static = config('auth.email_otp.static');

        if (is_string($static) && $static !== '' && preg_match('/^[0-9]{4}$/', $static)) {
            return $static;
        }

        if (! app()->isProduction()) {
            $fallback = config('auth.email_otp.static_dev_fallback');

            if (is_string($fallback) && preg_match('/^[0-9]{4}$/', $fallback)) {
                return $fallback;
            }
        }

        return str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }
}
