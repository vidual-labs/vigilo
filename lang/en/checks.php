<?php

return [
    'conversion_drop' => [
        'name' => 'Conversion drop',
        'description' => 'Conversions over the last 1 or 3 complete days are zero or far below the trailing baseline while the account keeps spending.',
        'advice' => 'Check conversion tracking first: does the tag/pixel still fire, are the Google Ads conversion actions and Meta pixel/CAPI events active, was the website or consent banner changed, and does the form still submit?',
        'fields' => [
            'windows_days' => 'Windows (days)',
            'baseline_days' => 'Baseline (days)',
            'drop_ratio' => 'Alert below (share of normal)',
            'min_window_spend' => 'Min. spend in window',
            'min_baseline_conversions' => 'Min. baseline conversions',
            'min_expected_conversions' => 'Min. expected conversions',
        ],
    ],
    'spend_without_leads' => [
        'name' => 'Spend without leads',
        'description' => 'Ads keep spending but the lead source has not received a single new lead for N hours — usually a broken form, webhook or sync.',
        'advice' => 'Send a test lead through the live form and follow it: form → webhook / OpenFlow / Meta Lead Ads sync → lodgely. Check lodgely\'s import log for errors.',
        'fields' => [
            'hours' => 'Hours without a lead',
            'min_spend' => 'Min. spend in that time',
        ],
    ],
    'connection_health' => [
        'name' => 'Connection health',
        'description' => 'Whether vigilo can still read the account: authentication, last successful fetch, Meta token expiry and account status.',
        'advice' => 'Re-authorise the account or renew the token (README: Connecting Google Ads / Meta). A disabled or unsettled ad account has to be fixed in the platform\'s billing or account settings.',
        'fields' => [
            'stale_hours' => 'Warn after (hours without data)',
            'stale_critical_hours' => 'Critical after (hours)',
            'token_warning_days' => 'Token warning (days before expiry)',
            'token_critical_days' => 'Token critical (days before expiry)',
        ],
    ],
    'budget_pacing' => [
        'name' => 'Budget pacing',
        'description' => 'Month-to-date spend compared with the pro-rata share of the monthly budget, per platform.',
        'advice' => 'Adjust daily budgets or bid targets in the platform — or update the monthly budget in vigilo if the plan changed.',
        'fields' => [
            'tolerance' => 'Tolerance (± share of plan)',
            'critical_overspend' => 'Critical when projected over by',
            'min_day_of_month' => 'Start checking on day',
        ],
    ],
    'ad_policy' => [
        'name' => 'Disapproved & limited ads',
        'description' => 'Ads in running campaigns that the platform disapproved or approved only with limitations.',
        'advice' => 'Open the ad in the platform, read the policy reason, then edit the ad or appeal. A campaign without any approved ad does not deliver at all.',
        'fields' => [
            'limited_severity' => 'Severity for limited ads',
        ],
    ],
    'cpl_spike' => [
        'name' => 'CPL spike',
        'description' => 'Cost per lead over the last 7 days compared with the 28 days before, per platform. Uses leads from the lead source when they can be attributed, platform conversions otherwise.',
        'advice' => 'Look at what changed in the last week: bids and budgets, audiences, creatives, placements, the landing page, competition. Compare cost per lead by campaign in the platform.',
        'fields' => [
            'recent_days' => 'Recent window (days)',
            'baseline_days' => 'Baseline (days)',
            'warning_factor' => 'Warning at factor',
            'critical_factor' => 'Critical at factor',
            'min_baseline_leads' => 'Min. baseline leads',
            'min_recent_spend' => 'Min. recent spend',
        ],
    ],
    'landing_page' => [
        'name' => 'Landing pages',
        'description' => 'Landing pages of active ads plus monitored URLs: reachability, HTTP status, redirect chains, response time and TLS certificate expiry. vigilo only loads the page — it never submits a form.',
        'advice' => 'Fix the page or point the ad at a working URL. Long redirect chains cost load time and can drop tracking parameters; renew certificates before they expire.',
        'fields' => [
            'slow_ms' => 'Slow above (ms)',
            'max_redirects' => 'Max. redirects',
            'tls_warning_days' => 'TLS warning (days)',
            'tls_critical_days' => 'TLS critical (days)',
            'failures_before_alert' => 'Failures in a row before alerting',
        ],
    ],
];
