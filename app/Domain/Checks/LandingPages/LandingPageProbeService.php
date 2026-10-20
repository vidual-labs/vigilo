<?php

namespace App\Domain\Checks\LandingPages;

use App\Domain\AdPlatforms\Services\FetchLogger;
use App\Models\Ad;
use App\Models\Client;
use App\Models\MonitoredUrl;
use App\Models\UrlProbe;

/**
 * Collects a client's landing pages (final URLs of active ads + monitored
 * URLs), probes each once, and stores the result for LandingPageCheck.
 */
class LandingPageProbeService
{
    public function __construct(private readonly FetchLogger $logger) {}

    public function prober(): UrlProber
    {
        return app(config('vigilo.mock_mode') ? MockUrlProber::class : HttpUrlProber::class);
    }

    /** @return int number of URLs probed */
    public function probeClient(Client $client): int
    {
        $urls = $this->collect($client);
        $prober = $this->prober();

        return $this->logger->record($client, $client->id, 'probe', function () use ($client, $urls, $prober) {
            foreach ($urls as $hash => ['url' => $url, 'origin' => $origin]) {
                $result = $prober->probe($url);
                $existing = UrlProbe::query()->where('client_id', $client->id)->where('url_hash', $hash)->first();
                $failures = $result->failed() ? (($existing->consecutive_failures ?? 0) + 1) : 0;

                UrlProbe::query()->updateOrCreate(
                    ['client_id' => $client->id, 'url_hash' => $hash],
                    [
                        'url' => $url,
                        'origin' => $origin,
                        'final_url' => $result->finalUrl,
                        'status_code' => $result->statusCode,
                        'redirect_chain' => $result->chain,
                        'redirect_count' => $result->redirectCount,
                        'response_ms' => $result->responseMs,
                        'tls_expires_at' => $result->tlsExpiresAt,
                        'error' => $result->error,
                        'consecutive_failures' => $failures,
                        'checked_at' => now(),
                    ],
                );
            }

            // URLs no longer used by any ad or monitor are dropped.
            UrlProbe::query()->where('client_id', $client->id)
                ->whereNotIn('url_hash', array_keys($urls) ?: [''])
                ->delete();

            return count($urls);
        }, fn () => count($urls), fn (int $count) => $count);
    }

    /** @return array<string, array{url: string, origin: string}> hash => url */
    public function collect(Client $client): array
    {
        $max = (int) config('vigilo.landing_pages.max_urls_per_client', 50);
        $urls = [];

        // Monitored URLs first: the operator asked for those explicitly.
        foreach (MonitoredUrl::query()->where('client_id', $client->id)->where('is_active', true)->orderBy('id')->get() as $monitored) {
            $this->push($urls, $monitored->url, 'monitored');
        }

        $ads = Ad::query()->where('client_id', $client->id)->where('is_active', true)
            ->whereHas('connection', fn ($q) => $q->where('is_active', true))
            ->orderBy('id')->get(['final_urls']);
        foreach ($ads as $ad) {
            foreach ($ad->final_urls ?? [] as $url) {
                $this->push($urls, (string) $url, 'ad');
            }
        }

        return array_slice($urls, 0, $max, true);
    }

    /** @param array<string, array{url: string, origin: string}> $urls */
    private function push(array &$urls, string $url, string $origin): void
    {
        $normalized = self::normalize($url);
        if ($normalized === null) {
            return;
        }
        $hash = sha1($normalized);
        $urls[$hash] ??= ['url' => $normalized, 'origin' => $origin];
    }

    /**
     * Drops fragments and click/UTM parameters so one page is probed once,
     * and skips URLs that still contain tracking templates ({lpurl}, …).
     */
    public static function normalize(string $url): ?string
    {
        $url = trim($url);
        if (! preg_match('#^https?://#i', $url) || str_contains($url, '{')) {
            return null;
        }
        $parts = parse_url($url);
        if (! isset($parts['host'])) {
            return null;
        }

        $query = [];
        parse_str($parts['query'] ?? '', $query);
        $strip = (array) config('vigilo.landing_pages.strip_params', []);
        $prefixes = (array) config('vigilo.landing_pages.strip_param_prefixes', []);
        foreach (array_keys($query) as $key) {
            $key = (string) $key;
            if (in_array(strtolower($key), $strip, true) || array_filter($prefixes, fn ($p) => str_starts_with(strtolower($key), $p))) {
                unset($query[$key]);
            }
        }
        ksort($query);

        return strtolower($parts['scheme'] ?? 'https').'://'.strtolower($parts['host'])
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .($parts['path'] ?? '/')
            .($query ? '?'.http_build_query($query) : '');
    }
}
