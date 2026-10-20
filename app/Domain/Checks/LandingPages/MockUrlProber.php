<?php

namespace App\Domain\Checks\LandingPages;

use App\Domain\AdPlatforms\Mock\Seeded;
use Carbon\CarbonImmutable;

/**
 * Mock-mode prober: no network. Every URL answers 200 quickly with a
 * certificate valid for months — except a few paths/hosts the demo data
 * uses to show each landing-page finding:
 *
 *   path /angebot-sommer      → 404 (seasonal page taken offline)
 *   path /webinar             → three redirects before the page
 *   path /rechner             → 4.2 s response
 *   host starting "termine."  → certificate expires in 5 days
 *   host starting "alt."      → connection refused
 */
class MockUrlProber implements UrlProber
{
    public function probe(string $url): ProbeResult
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);
        $tls = str_starts_with($url, 'https://')
            ? CarbonImmutable::now()->addDays(str_starts_with($host, 'termine.') ? 5 : 70)->startOfHour()
            : null;
        $fast = (int) (180 + 400 * Seeded::float('ms', $url));

        if (str_starts_with($host, 'alt.')) {
            return new ProbeResult($url, null, [['url' => $url, 'status' => null]], 0, null, null, 'cURL 7: Failed to connect: Connection refused');
        }

        if (str_starts_with($path, '/angebot-sommer')) {
            return new ProbeResult($url, 404, [['url' => $url, 'status' => 404]], 0, $fast, $tls, null);
        }

        if (str_starts_with($path, '/webinar')) {
            $base = 'https://'.$host;
            $chain = [
                ['url' => $url, 'status' => 301],
                ['url' => $base.'/events/webinar', 'status' => 302],
                ['url' => $base.'/events/webinar/', 'status' => 301],
                ['url' => $base.'/events/webinar/anmeldung', 'status' => 200],
            ];

            return new ProbeResult($base.'/events/webinar/anmeldung', 200, $chain, 3, $fast + 600, $tls, null);
        }

        if (str_starts_with($path, '/rechner')) {
            return new ProbeResult($url, 200, [['url' => $url, 'status' => 200]], 0, 4200, $tls, null);
        }

        return new ProbeResult($url, 200, [['url' => $url, 'status' => 200]], 0, $fast, $tls, null);
    }
}
