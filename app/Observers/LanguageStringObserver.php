<?php

namespace App\Observers;

use App\Models\LanguageString;
use App\Services\ActivityLogService;
use App\Support\ApplicationCache;

class LanguageStringObserver
{
    protected ActivityLogService $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    /**
     * Handle the LanguageString "created" event.
     */
    public function created(LanguageString $languageString): void
    {
        $this->activityLogService->logCreated('languages', $languageString);
        ApplicationCache::forgetTranslations((string) $languageString->lang);
    }

    /**
     * Handle the LanguageString "updated" event.
     */
    public function updated(LanguageString $languageString): void
    {
        if ($languageString->wasChanged() && count($languageString->getChanges()) > 0) {
            $this->activityLogService->logUpdated('languages', $languageString);
        }

        if ($languageString->wasChanged(['lang', 'file', 'key', 'value'])) {
            ApplicationCache::forgetTranslations((string) $languageString->lang);
        }
    }

    /**
     * Handle the LanguageString "deleted" event.
     */
    public function deleted(LanguageString $languageString): void
    {
        $this->activityLogService->logDeleted('languages', $languageString);
        ApplicationCache::forgetTranslations((string) $languageString->lang);
    }
}

