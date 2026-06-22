<?php

namespace App\Services\CallLog;

use App\Models\User;
use Illuminate\Support\Str;

final class CallLogSyncTokenService
{
    public function issue(User $user): string
    {
        $plain = Str::random(64);

        $user->forceFill([
            'call_log_sync_token_hash' => $this->digest($plain),
        ])->save();

        return $plain;
    }

    public function validate(User $user, string $plainToken): bool
    {
        $stored = $user->call_log_sync_token_hash;

        if (! is_string($stored) || $stored === '') {
            return false;
        }

        return hash_equals($stored, $this->digest($plainToken));
    }

    private function digest(string $plainToken): string
    {
        return hash_hmac('sha256', $plainToken, (string) config('app.key'));
    }
}
