<?php

namespace App\Observers;

use App\Models\CallRecording;
use App\Support\ApplicationCache;

class CallRecordingObserver
{
    public function saved(CallRecording $recording): void
    {
        ApplicationCache::forgetDashboardForTenant((int) $recording->tenant_id);
        ApplicationCache::bumpDashboardMaster();
    }

    public function deleted(CallRecording $recording): void
    {
        ApplicationCache::forgetDashboardForTenant((int) $recording->tenant_id);
        ApplicationCache::bumpDashboardMaster();
    }
}
