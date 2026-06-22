<?php

return [
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],
    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'resend' => [
        'key' => env('RESEND_KEY'),
    ],
    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'gpt_model' => env('OPENAI_GPT_MODEL', 'gpt-4'),
        'whisper_model' => env('OPENAI_WHISPER_MODEL', 'whisper-1'),
    ],

    // System-wide Supabase Storage fallback (used when a tenant has no override).
    'supabase' => [
        'url' => env('SUPABASE_URL'),
        'key' => env('SUPABASE_KEY'),
        'bucket' => env('SUPABASE_BUCKET'),
        'voicenotes_bucket' => env('SUPABASE_VOICENOTES_BUCKET', 'voicenotes'),
    ],

    'libreoffice' => [
        'path' => env('LIBREOFFICE_PATH'),
    ],

    // Lead Connector (GoHighLevel Marketplace API 2.0) — see https://marketplace.gohighlevel.com/docs/ghl/
    'gohighlevel' => [
        'api_base' => env('GOHIGHLEVEL_API_BASE', 'https://services.leadconnectorhq.com'),
        'api_version' => env('GOHIGHLEVEL_API_VERSION', '2023-02-21'),
    ],

    /*
     * Zoho CRM + OAuth (same token endpoint pattern across Zoho products).
     * Generate tokens: POST {Accounts_URL}/oauth/v2/token with form body:
     * grant_type=authorization_code|refresh_token, client_id, client_secret, redirect_uri (code flow), code or refresh_token.
     *
     * Domain-specific Accounts URLs (use the same region for authorize + token + api_domain):
     * US https://accounts.zoho.com | AU https://accounts.zoho.com.au | EU https://accounts.zoho.eu
     * IN https://accounts.zoho.in | CN https://accounts.zoho.com.cn | JP https://accounts.zoho.jp
     *
     * @see https://www.zoho.com/assist/api/generate-access-token.html
     */
    'zoho' => [
        'crm_api_base' => env('ZOHO_CRM_API_BASE', 'https://www.zohoapis.in'),
        'crm_api_version' => env('ZOHO_CRM_API_VERSION', 'v8'),
        'accounts_token_url' => env('ZOHO_ACCOUNTS_TOKEN_URL')
            ?: rtrim((string) env('ZOHO_ACCOUNTS_BASE', 'https://accounts.zoho.in'), '/').'/oauth/v2/token',
        'accounts_authorize_url' => env('ZOHO_ACCOUNTS_AUTHORIZE_URL')
            ?: rtrim((string) env('ZOHO_ACCOUNTS_BASE', 'https://accounts.zoho.in'), '/').'/oauth/v2/auth',
        /**
         * Comma-separated scopes for the browser OAuth step (must match the Zoho API client).
         * `ZohoCRM.settings.tags.READ` is required for CRM Settings Tags (e.g. list tags API).
         */
        'oauth_scopes' => env(
            'ZOHO_OAUTH_SCOPES',
            'ZohoCRM.users.READ,ZohoCRM.modules.ALL,ZohoCRM.settings.tags.READ'
        ),
        'oauth_client_id' => env('ZOHO_OAUTH_CLIENT_ID'),
        'oauth_client_secret' => env('ZOHO_OAUTH_CLIENT_SECRET'),
        'oauth_redirect_uri' => env('ZOHO_OAUTH_REDIRECT_URI'),
        /** Log Zoho Accounts token POST (redacted) to storage/logs — enable for debugging exchange/refresh. */
        'log_token_http' => env('ZOHO_LOG_TOKEN_HTTP', env('APP_DEBUG', false)),
    ],
];
