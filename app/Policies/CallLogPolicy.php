<?php

namespace App\Policies;

use App\Models\CallLog;
use App\Models\User;

class CallLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('call-logs.view');
    }

    public function view(User $user, CallLog $callLog): bool
    {
        if (! $user->can('call-logs.view')) {
            return false;
        }

        if ($user->isMaster()) {
            return true;
        }

        $callLog->loadMissing('user');

        return $callLog->user !== null
            && (int) $callLog->user->tenant_id === (int) $user->tenant_id;
    }
}
