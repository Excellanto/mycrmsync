<?php

namespace App\Observers;

use App\Models\Tenant;
use App\Support\ApplicationCache;

class TenantObserver
{
    public function updated(Tenant $tenant): void
    {
        if ($tenant->wasChanged('status')) {
            ApplicationCache::forgetAuthForTenantUsers((int) $tenant->id);
        }

        if (! $tenant->wasChanged([
            'company_logo_path',
            'integration',
            'integration_status',
            'company_name',
            'account_type',
            'email_ingestion_enabled',
        ])) {
            return;
        }

        ApplicationCache::forgetAuthForTenantUsers((int) $tenant->id);
        ApplicationCache::forgetPlatformHasMyCrmSyncTenant();

        if ($tenant->wasChanged(['integration', 'integration_status'])) {
            ApplicationCache::forgetDashboardForTenant((int) $tenant->id);
        }
    }
}
