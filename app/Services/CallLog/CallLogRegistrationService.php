<?php

namespace App\Services\CallLog;

use App\Models\CallLog;
use App\Models\User;
use App\Support\ApplicationCache;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CallLogRegistrationService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{id: string, user_id: string, sync_fingerprint: string|null, status: string, created: bool}
     */
    public function register(string $locationId, string $userId, array $payload): array
    {
        $attributes = $this->buildAttributes($locationId, $userId, $payload);

        if ($attributes['sync_fingerprint'] !== null) {
            $existing = CallLog::query()
                ->where('location_id', $locationId)
                ->where('user_id', $userId)
                ->where('sync_fingerprint', $attributes['sync_fingerprint'])
                ->first();

            if ($existing !== null) {
                return $this->resultPayload($existing, false);
            }
        }

        try {
            $callLog = CallLog::query()->create($attributes);
        } catch (QueryException $e) {
            if ($this->isDuplicateFingerprint($e) && $attributes['sync_fingerprint'] !== null) {
                $existing = CallLog::query()
                    ->where('location_id', $locationId)
                    ->where('user_id', $userId)
                    ->where('sync_fingerprint', $attributes['sync_fingerprint'])
                    ->first();

                if ($existing !== null) {
                    return $this->resultPayload($existing, false);
                }
            }

            throw $e;
        }

        $this->forgetDashboardCachesForUser($userId);

        return $this->resultPayload($callLog, true);
    }

    /**
     * @param  list<array<string, mixed>>  $calls
     * @return array{
     *     created: int,
     *     skipped_duplicate: int,
     *     failed: int,
     *     results: list<array<string, mixed>>
     * }
     */
    public function registerBulk(string $locationId, string $userId, array $calls): array
    {
        $created = 0;
        $skippedDuplicate = 0;
        $failed = 0;
        $results = [];

        foreach ($calls as $index => $callPayload) {
            if (! is_array($callPayload)) {
                $failed++;
                $results[] = [
                    'index' => $index,
                    'success' => false,
                    'message' => 'Each call entry must be an object.',
                ];

                continue;
            }

            try {
                $this->validateCallPayload($callPayload);
                $result = $this->register($locationId, $userId, $callPayload);

                if ($result['created']) {
                    $created++;
                    $message = 'Call log registered';
                } else {
                    $skippedDuplicate++;
                    $message = 'Call log already exists';
                }

                $results[] = [
                    'index' => $index,
                    'success' => true,
                    'message' => $message,
                    'data' => $result,
                ];
            } catch (ValidationException $e) {
                $failed++;
                $results[] = [
                    'index' => $index,
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Validation failed.',
                    'errors' => $e->errors(),
                ];
            } catch (Throwable $e) {
                $failed++;
                $results[] = [
                    'index' => $index,
                    'success' => false,
                    'message' => 'Failed to register call log.',
                ];
                report($e);
            }
        }

        return [
            'created' => $created,
            'skipped_duplicate' => $skippedDuplicate,
            'failed' => $failed,
            'results' => $results,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function validateCallPayload(array $payload): array
    {
        return validator($payload, $this->callRules())->validate();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function callRules(): array
    {
        return [
            'direction' => ['required', 'string', 'in:INCOMING,OUTGOING,MISSED,UNKNOWN'],
            'phone_raw' => ['nullable', 'string', 'max:64'],
            'phone_e164' => ['nullable', 'string', 'max:32'],
            'contact_id' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'duration_sec' => ['nullable', 'integer', 'min:0'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date'],
            'sim_account_id' => ['nullable', 'string', 'max:128'],
            'sync_fingerprint' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function buildAttributes(string $locationId, string $userId, array $payload): array
    {
        $validated = $this->validateCallPayload($payload);

        $durationSec = isset($validated['duration_sec']) ? (int) $validated['duration_sec'] : null;
        $endedAt = $this->parseTimestamp($validated['ended_at'] ?? null);

        if ($durationSec === 0) {
            $endedAt = null;
        }

        return [
            'id' => (string) Str::uuid(),
            'location_id' => $locationId !== '' ? $locationId : null,
            'user_id' => $userId,
            'direction' => (string) $validated['direction'],
            'phone_raw' => $validated['phone_raw'] ?? null,
            'phone_e164' => $this->normalizeE164(
                isset($validated['phone_e164']) ? (string) $validated['phone_e164'] : null,
                isset($validated['phone_raw']) ? (string) $validated['phone_raw'] : null,
            ),
            'contact_id' => $validated['contact_id'] ?? null,
            'contact_name' => $validated['contact_name'] ?? null,
            'duration_sec' => $durationSec,
            'started_at' => $this->parseTimestamp($validated['started_at'] ?? null),
            'ended_at' => $endedAt,
            'sim_account_id' => $validated['sim_account_id'] ?? null,
            'status' => (string) ($validated['status'] ?? 'synced_device'),
            'sync_fingerprint' => (string) $validated['sync_fingerprint'],
            'created_at' => now(),
        ];
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    private function normalizeE164(?string $phoneE164, ?string $phoneRaw): ?string
    {
        if ($phoneE164 !== null && $phoneE164 !== '') {
            $trimmed = trim($phoneE164);
            if (str_starts_with($trimmed, '+')) {
                $digits = preg_replace('/\D/', '', substr($trimmed, 1));

                return $digits !== '' ? '+'.$digits : $trimmed;
            }

            $digits = preg_replace('/\D/', '', $trimmed);
            if ($digits === '') {
                return $trimmed;
            }

            if (strlen($digits) === 10) {
                return '+91'.$digits;
            }

            return '+'.$digits;
        }

        if ($phoneRaw === null || $phoneRaw === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phoneRaw);
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10) {
            return '+91'.$digits;
        }

        if (strlen($digits) > 10 && str_starts_with($digits, '91')) {
            return '+'.$digits;
        }

        return '+'.$digits;
    }

    private function isDuplicateFingerprint(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');

        return in_array($sqlState, ['23505', '23000'], true);
    }

    private function forgetDashboardCachesForUser(string $userId): void
    {
        $tenantId = User::query()
            ->whereKey(is_numeric($userId) ? (int) $userId : $userId)
            ->value('tenant_id');

        if ($tenantId !== null) {
            ApplicationCache::forgetDashboardForTenant((int) $tenantId);
        }

        ApplicationCache::bumpDashboardMaster();
    }

    /**
     * @return array{id: string, user_id: string, sync_fingerprint: string|null, status: string, created: bool}
     */
    private function resultPayload(CallLog $callLog, bool $created): array
    {
        return [
            'id' => (string) $callLog->id,
            'user_id' => (string) $callLog->user_id,
            'sync_fingerprint' => $callLog->sync_fingerprint,
            'status' => (string) $callLog->status,
            'created' => $created,
        ];
    }
}
