<?php

namespace App\Observers;

use App\Models\Integration;
use App\Support\ApplicationCache;

class IntegrationObserver
{
    public function saved(Integration $integration): void
    {
        if ($integration->wasChanged(['name', 'slug', 'type', 'enabled'])) {
            ApplicationCache::forgetEnabledIntegrations();
        }
    }

    public function deleted(Integration $integration): void
    {
        ApplicationCache::forgetEnabledIntegrations();
    }
}
