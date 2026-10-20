<?php

return [
    'conversion_drop' => [
        'name' => 'Conversion-Einbruch',
        'description' => 'Die Conversions der letzten 1 oder 3 vollen Tage liegen bei null oder weit unter dem Durchschnitt davor, während das Konto weiter Geld ausgibt.',
        'advice' => 'Zuerst das Conversion-Tracking prüfen: Feuert Tag bzw. Pixel noch, sind die Google-Ads-Conversion-Aktionen und Meta-Pixel/CAPI-Events aktiv, wurde die Website oder das Consent-Banner geändert, und lässt sich das Formular noch absenden?',
        'fields' => [
            'windows_days' => 'Zeitfenster (Tage)',
            'baseline_days' => 'Vergleichszeitraum (Tage)',
            'drop_ratio' => 'Alarm unter (Anteil vom Normalwert)',
            'min_window_spend' => 'Mindestausgaben im Fenster',
            'min_baseline_conversions' => 'Min. Conversions im Vergleich',
            'min_expected_conversions' => 'Min. erwartete Conversions',
        ],
    ],
    'spend_without_leads' => [
        'name' => 'Ausgaben ohne Leads',
        'description' => 'Die Anzeigen geben weiter Geld aus, aber in der Lead-Quelle ist seit N Stunden kein einziger neuer Lead angekommen – meist ein kaputtes Formular, ein Webhook oder ein Sync.',
        'advice' => 'Einen Test-Lead über das Live-Formular senden und verfolgen: Formular → Webhook / OpenFlow / Meta-Lead-Ads-Sync → lodgely. Im Import-Log von lodgely nach Fehlern sehen.',
        'fields' => [
            'hours' => 'Stunden ohne Lead',
            'min_spend' => 'Mindestausgaben in dieser Zeit',
        ],
    ],
    'connection_health' => [
        'name' => 'Verbindungsstatus',
        'description' => 'Ob vigilo das Konto noch lesen kann: Anmeldung, letzter erfolgreicher Abruf, Ablauf des Meta-Tokens und Kontostatus.',
        'advice' => 'Konto neu autorisieren oder Token erneuern (README: Google Ads / Meta verbinden). Ein gesperrtes oder unbezahltes Werbekonto muss in den Abrechnungs- bzw. Kontoeinstellungen der Plattform behoben werden.',
        'fields' => [
            'stale_hours' => 'Warnung nach (Stunden ohne Daten)',
            'stale_critical_hours' => 'Kritisch nach (Stunden)',
            'token_warning_days' => 'Token-Warnung (Tage vor Ablauf)',
            'token_critical_days' => 'Token kritisch (Tage vor Ablauf)',
        ],
    ],
    'budget_pacing' => [
        'name' => 'Budget-Pacing',
        'description' => 'Ausgaben seit Monatsbeginn im Vergleich zum anteiligen Monatsbudget, je Plattform.',
        'advice' => 'Tagesbudgets oder Gebotsziele in der Plattform anpassen – oder das Monatsbudget in vigilo aktualisieren, wenn sich der Plan geändert hat.',
        'fields' => [
            'tolerance' => 'Toleranz (± Anteil vom Plan)',
            'critical_overspend' => 'Kritisch bei Hochrechnung über Budget um',
            'min_day_of_month' => 'Prüfen ab Monatstag',
        ],
    ],
    'ad_policy' => [
        'name' => 'Abgelehnte & eingeschränkte Anzeigen',
        'description' => 'Anzeigen in laufenden Kampagnen, die die Plattform abgelehnt oder nur eingeschränkt freigegeben hat.',
        'advice' => 'Die Anzeige in der Plattform öffnen, den Richtlinien-Grund lesen und die Anzeige bearbeiten oder Einspruch einlegen. Eine Kampagne ohne freigegebene Anzeige liefert gar nicht aus.',
        'fields' => [
            'limited_severity' => 'Schweregrad bei Einschränkung',
        ],
    ],
    'cpl_spike' => [
        'name' => 'CPL-Anstieg',
        'description' => 'Kosten pro Lead der letzten 7 Tage im Vergleich zu den 28 Tagen davor, je Plattform. Nutzt Leads aus der Lead-Quelle, wenn sie zugeordnet werden können, sonst die Conversions der Plattform.',
        'advice' => 'Prüfen, was sich in der letzten Woche geändert hat: Gebote und Budgets, Zielgruppen, Creatives, Platzierungen, Landingpage, Wettbewerb. Die Kosten pro Lead je Kampagne in der Plattform vergleichen.',
        'fields' => [
            'recent_days' => 'Aktuelles Fenster (Tage)',
            'baseline_days' => 'Vergleichszeitraum (Tage)',
            'warning_factor' => 'Warnung ab Faktor',
            'critical_factor' => 'Kritisch ab Faktor',
            'min_baseline_leads' => 'Min. Leads im Vergleich',
            'min_recent_spend' => 'Mindestausgaben aktuell',
        ],
    ],
    'landing_page' => [
        'name' => 'Landingpages',
        'description' => 'Landingpages aktiver Anzeigen und überwachte URLs: Erreichbarkeit, HTTP-Status, Weiterleitungsketten, Antwortzeit und Ablauf des TLS-Zertifikats. vigilo lädt die Seite nur – es sendet nie ein Formular ab.',
        'advice' => 'Die Seite reparieren oder die Anzeige auf eine funktionierende URL zeigen lassen. Lange Weiterleitungsketten kosten Ladezeit und können Tracking-Parameter verlieren; Zertifikate rechtzeitig erneuern.',
        'fields' => [
            'slow_ms' => 'Langsam ab (ms)',
            'max_redirects' => 'Max. Weiterleitungen',
            'tls_warning_days' => 'TLS-Warnung (Tage)',
            'tls_critical_days' => 'TLS kritisch (Tage)',
            'failures_before_alert' => 'Fehlversuche in Folge bis Alarm',
        ],
    ],
];
