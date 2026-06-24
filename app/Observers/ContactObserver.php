<?php

namespace App\Observers;

use App\Models\Contact;
use App\Support\ApplicationCache;

class ContactObserver
{
    public function saved(Contact $contact): void
    {
        $this->forgetContactCaches($contact);
    }

    public function deleted(Contact $contact): void
    {
        $this->forgetContactCaches($contact);
    }

    private function forgetContactCaches(Contact $contact): void
    {
        ApplicationCache::forgetContactTags((int) $contact->tenant_id);
        ApplicationCache::forgetDashboardForTenant((int) $contact->tenant_id);
    }
}
