<?php

return [
    'conversion_drop' => [
        'zero' => '{1} :account: gestern keine Conversions trotz :spend Ausgaben|[2,*] :account: keine Conversions in den letzten :days Tagen trotz :spend Ausgaben',
        'drop' => '{1} :account: gestern nur :ratio der üblichen Conversions|[2,*] :account: nur :ratio der üblichen Conversions in den letzten :days Tagen',
    ],
    'spend_without_leads' => [
        'no_leads' => 'Seit :hours Stunden kein neuer Lead, obwohl :spend ausgegeben wurden',
    ],
    'connection_health' => [
        'auth_failed' => ':account: Anmeldung fehlgeschlagen',
        'stale' => ':account: seit :hours Stunden kein erfolgreicher Abruf',
        'never_fetched' => ':account: noch nie erfolgreich abgerufen (angelegt vor :hours Stunden)',
        'token_expiring' => '{0} :account: Zugriffstoken läuft heute ab|{1} :account: Zugriffstoken läuft morgen ab|[2,*] :account: Zugriffstoken läuft in :days Tagen ab',
        'token_expired' => ':account: Zugriffstoken ist abgelaufen',
        'account_inactive' => ':account: Werbekonto ist :status',
    ],
    'budget_pacing' => [
        'over_budget' => ':platform: Monatsbudget bereits ausgeschöpft (:spend von :budget)',
        'overpacing' => ':platform: Ausgaben über Plan (:pace, Hochrechnung :projected von :budget)',
        'underpacing' => ':platform: Ausgaben unter Plan (:pace, Hochrechnung :projected von :budget)',
    ],
    'ad_policy' => [
        'disapproved' => '{1} :account: 1 Anzeige abgelehnt|[2,*] :account: :count Anzeigen abgelehnt',
        'campaign_blocked' => ':account: :campaigns Kampagne(n) ohne freigegebene Anzeige (:count Anzeigen abgelehnt)',
        'limited' => '{1} :account: 1 Anzeige eingeschränkt freigegeben|[2,*] :account: :count Anzeigen eingeschränkt freigegeben',
    ],
    'cpl_spike' => [
        'spike' => ':platform: Kosten pro Lead :factor höher (:cpl statt :baseline)',
    ],
    'landing_page' => [
        'down' => ':url ist nicht erreichbar (:status)',
        'redirects' => ':url leitet :count-mal weiter, bevor die Seite lädt',
        'offsite' => ':url leitet auf eine andere Website um (:target)',
        'insecure' => ':url wechselt von HTTPS auf HTTP',
        'slow' => ':url lädt langsam (:seconds s)',
        'tls_expiring' => '{0} TLS-Zertifikat für :host läuft heute ab|{1} TLS-Zertifikat für :host läuft morgen ab|[2,*] TLS-Zertifikat für :host läuft in :days Tagen ab',
        'tls_expired' => 'TLS-Zertifikat für :host ist abgelaufen',
    ],
];
