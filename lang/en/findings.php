<?php

return [
    'conversion_drop' => [
        'zero' => '{1} :account: no conversions yesterday despite :spend spend|[2,*] :account: no conversions in the last :days days despite :spend spend',
        'drop' => '{1} :account: conversions yesterday at :ratio of normal|[2,*] :account: conversions at :ratio of normal over the last :days days',
    ],
    'spend_without_leads' => [
        'no_leads' => 'No new lead for :hours hours while :spend was spent',
    ],
    'connection_health' => [
        'auth_failed' => ':account: authentication failed',
        'stale' => ':account: no successful fetch for :hours hours',
        'never_fetched' => ':account: never fetched successfully (added :hours hours ago)',
        'token_expiring' => '{0} :account: access token expires today|{1} :account: access token expires tomorrow|[2,*] :account: access token expires in :days days',
        'token_expired' => ':account: access token has expired',
        'account_inactive' => ':account: ad account is :status',
    ],
    'budget_pacing' => [
        'over_budget' => ':platform: monthly budget already spent (:spend of :budget)',
        'overpacing' => ':platform: spending ahead of plan (:pace, heading for :projected of :budget)',
        'underpacing' => ':platform: spending behind plan (:pace, heading for :projected of :budget)',
    ],
    'ad_policy' => [
        'disapproved' => '{1} :account: 1 ad disapproved|[2,*] :account: :count ads disapproved',
        'campaign_blocked' => ':account: :campaigns campaign(s) without any approved ad (:count ads disapproved)',
        'limited' => '{1} :account: 1 ad approved with limitations|[2,*] :account: :count ads approved with limitations',
    ],
    'cpl_spike' => [
        'spike' => ':platform: cost per lead up :factor (:cpl vs. :baseline)',
    ],
    'landing_page' => [
        'down' => ':url is unreachable (:status)',
        'redirects' => ':url redirects :count times before the page loads',
        'offsite' => ':url redirects to another site (:target)',
        'insecure' => ':url falls back from HTTPS to HTTP',
        'slow' => ':url is slow (:seconds s)',
        'tls_expiring' => '{0} TLS certificate for :host expires today|{1} TLS certificate for :host expires tomorrow|[2,*] TLS certificate for :host expires in :days days',
        'tls_expired' => 'TLS certificate for :host has expired',
    ],
];
