<?php

/*
|--------------------------------------------------------------------------
| vigilo configuration
|--------------------------------------------------------------------------
|
| Every value here can be set from the environment; .env.example documents
| each variable. Check thresholds below are the *global defaults* — operators
| override them per install on /checks and per client on the client settings
| page (resolution order: client → install → this file).
|
*/

return [

    // Read once from composer.json so the package version stays the single
    // source of truth (footer badge, webhook payloads, User-Agent).
    'version' => (function () {
        $path = base_path('composer.json');
        if (! is_file($path)) {
            return 'dev';
        }
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? ($data['version'] ?? 'dev') : 'dev';
    })(),

    // Mock mode swaps every outbound integration (Google Ads, Meta, lodgely,
    // landing-page probes) for deterministic simulators. ON by default so a
    // fresh install works without credentials. Turn it off once real
    // credentials are configured below.
    'mock_mode' => (bool) env('VIGILO_MOCK_MODE', true),

    // Load the demo clients on first boot (only when mock mode is on and the
    // install has no clients yet). `php artisan vigilo:demo:remove` unloads them.
    'demo_data' => (bool) env('VIGILO_DEMO_DATA', true),

    // Timezone used for the schedule and for new clients. Each client has its
    // own timezone for "yesterday" / month-to-date maths.
    'timezone' => env('VIGILO_TIMEZONE', 'Europe/Berlin'),

    'schedule' => [
        // Local time (VIGILO_TIMEZONE) of the daily fetch + checks + digest run.
        'daily_run_at' => env('VIGILO_DAILY_RUN_AT', '06:00'),
    ],

    // First operator, created on first boot by the Docker entrypoint
    // (`php artisan vigilo:admin:ensure`). Leave the password empty to have a
    // strong one generated and printed once in `docker compose logs app`.
    'admin' => [
        'name' => env('ADMIN_NAME', 'Admin'),
        'email' => env('ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('ADMIN_PASSWORD', ''),
    ],

    // Read-only by design: vigilo never writes to an ad platform. Only the
    // `adwords` scope (Google has no narrower Ads scope) and `ads_read` (Meta)
    // are needed.
    'google_ads' => [
        'api_version' => env('VIGILO_GOOGLE_ADS_API_VERSION', 'v26'),
        'base_url' => env('VIGILO_GOOGLE_ADS_BASE_URL', 'https://googleads.googleapis.com'),
        'token_url' => 'https://oauth2.googleapis.com/token',
        'developer_token' => env('VIGILO_GOOGLE_ADS_DEVELOPER_TOKEN', ''),
        'client_id' => env('VIGILO_GOOGLE_ADS_CLIENT_ID', ''),
        'client_secret' => env('VIGILO_GOOGLE_ADS_CLIENT_SECRET', ''),
        // Agency-wide refresh token (the Google user that can see the manager
        // account). A connection may override it with its own token.
        'refresh_token' => env('VIGILO_GOOGLE_ADS_REFRESH_TOKEN', ''),
        // Manager (MCC) id used as login-customer-id when a connection has none.
        'login_customer_id' => env('VIGILO_GOOGLE_ADS_LOGIN_CUSTOMER_ID', ''),
    ],

    'meta' => [
        'api_version' => env('VIGILO_META_ADS_API_VERSION', 'v26.0'),
        'base_url' => env('VIGILO_META_ADS_BASE_URL', 'https://graph.facebook.com'),
        // Agency-wide system-user token with `ads_read`. A connection may
        // override it with its own token.
        'access_token' => env('VIGILO_META_ADS_ACCESS_TOKEN', ''),
        // Optional: when set, every call carries an appsecret_proof.
        'app_secret' => env('VIGILO_META_APP_SECRET', ''),
        // Which Meta action counts as a conversion. The first type present in
        // a row wins, so aggregate types must come before their parts.
        'conversion_action_types' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'VIGILO_META_CONVERSION_ACTION_TYPES',
            'lead,onsite_conversion.lead_grouped,offsite_conversion.fb_pixel_lead'
        ))))),
    ],

    // lodgely lead source (see docs/LODGELY-CONTRACT.md). Off until lodgely
    // ships the read API described in the contract.
    'lodgely' => [
        'enabled' => (bool) env('VIGILO_LODGELY_ENABLED', false),
        'url' => rtrim((string) env('VIGILO_LODGELY_URL', ''), '/'),
        'token' => env('VIGILO_LODGELY_API_TOKEN', ''),
        'page_size' => 500,
        'max_pages_per_fetch' => 40,
        // How far back the very first fetch for a client reaches.
        'initial_lookback_days' => 60,
    ],

    // Outbound HTTP to ad platforms and lodgely.
    'http' => [
        'timeout' => (int) env('VIGILO_HTTP_TIMEOUT', 30),
        'retries' => (int) env('VIGILO_HTTP_RETRIES', 3),
        // Base delay for exponential backoff (attempt n waits base * 3^(n-1)).
        'backoff_ms' => 1000,
        // Never wait longer than this for a Retry-After / rate-limit window.
        'max_wait_seconds' => 60,
    ],

    // Days of ad metrics fetched per run (covers the 28+7 day CPL window and
    // late conversion attribution).
    'fetch' => [
        'metrics_days' => (int) env('VIGILO_FETCH_METRICS_DAYS', 40),
    ],

    'landing_pages' => [
        'timeout' => 10,
        'max_redirects' => 5,
        'max_urls_per_client' => 50,
        // Query parameters stripped before probing / de-duplicating URLs.
        'strip_params' => ['gclid', 'gbraid', 'wbraid', 'fbclid', 'msclkid', 'dclid'],
        'strip_param_prefixes' => ['utm_'],
    ],

    'notifications' => [
        // Mail critical findings the moment they are raised outside the daily
        // run (the daily digest follows a scheduled run within minutes anyway).
        'critical_immediately' => (bool) env('VIGILO_NOTIFY_CRITICAL_IMMEDIATELY', true),
        // Send the digest even when nothing is open ("all clear").
        'digest_when_empty' => (bool) env('VIGILO_DIGEST_WHEN_EMPTY', true),
    ],

    'retention' => [
        'fetch_logs_days' => (int) env('VIGILO_RETENTION_FETCH_LOGS_DAYS', 90),
        'metrics_days' => (int) env('VIGILO_RETENTION_METRICS_DAYS', 400),
        'leads_days' => (int) env('VIGILO_RETENTION_LEADS_DAYS', 400),
        'resolved_findings_days' => (int) env('VIGILO_RETENTION_RESOLVED_FINDINGS_DAYS', 365),
        'runs_days' => 90,
    ],

    /*
    | Check defaults. Each check's class declares which keys it reads; the
    | values here are what a fresh install uses. Money is in the client's
    | currency, durations in hours/days as named.
    */
    'checks' => [
        'conversion_drop' => [
            'enabled' => true,
            'windows_days' => [1, 3],
            'baseline_days' => 14,
            'drop_ratio' => 0.5,
            'min_window_spend' => 50,
            'min_baseline_conversions' => 5,
            'min_expected_conversions' => 2,
        ],
        'spend_without_leads' => [
            'enabled' => true,
            'hours' => 48,
            'min_spend' => 30,
        ],
        'connection_health' => [
            'enabled' => true,
            'stale_hours' => 26,
            'stale_critical_hours' => 72,
            'token_warning_days' => 14,
            'token_critical_days' => 3,
        ],
        'budget_pacing' => [
            'enabled' => true,
            'tolerance' => 0.15,
            'critical_overspend' => 0.25,
            'min_day_of_month' => 3,
        ],
        'ad_policy' => [
            'enabled' => true,
            'limited_severity' => 'info',
        ],
        'cpl_spike' => [
            'enabled' => true,
            'recent_days' => 7,
            'baseline_days' => 28,
            'warning_factor' => 1.5,
            'critical_factor' => 2.0,
            'min_baseline_leads' => 5,
            'min_recent_spend' => 100,
        ],
        'landing_page' => [
            'enabled' => true,
            'slow_ms' => 3000,
            'max_redirects' => 2,
            'tls_warning_days' => 14,
            'tls_critical_days' => 3,
            'failures_before_alert' => 2,
        ],
    ],
];
