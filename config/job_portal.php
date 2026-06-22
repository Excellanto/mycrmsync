<?php

return [

    'resume_disk' => env('JOB_PORTAL_RESUME_DISK', 'local'),

    'resume_log_channel' => env('JOB_PORTAL_RESUME_LOG_CHANNEL', 'stack'),

    /*
    | When a job-portal resume batch has no admin user, tenant is taken from the
    | candidate's first linked tenant, then this ID, then Master/first tenant fallback.
    */
    'default_tenant_id' => env('JOB_PORTAL_DEFAULT_TENANT_ID'),

];
