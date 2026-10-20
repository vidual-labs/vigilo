<?php

namespace App\Domain\Checks\LandingPages;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class HttpUrlProber implements UrlProber
{
    public function __construct(private readonly TlsInspector $tls) {}

    public function probe(string $url): ProbeResult
    {
        $timeout = (int) config('vigilo.landing_pages.timeout', 10);
        $maxRedirects = (int) config('vigilo.landing_pages.max_redirects', 5);
        $chain = [];
        $current = $url;
        $status = null;
        $error = null;
        $start = hrtime(true);

        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            try {
                $response = Http::timeout($timeout)
                    ->connectTimeout(min(5, $timeout))
                    ->withUserAgent('vigilo-linkcheck/'.config('vigilo.version').' (+'.config('app.url').')')
                    ->withHeaders(['Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8'])
                    ->withOptions(['allow_redirects' => false, 'stream' => true])
                    ->get($current);
            } catch (ConnectionException $e) {
                $error = self::shortError($e->getMessage());
                $chain[] = ['url' => $current, 'status' => null];
                break;
            } catch (Throwable $e) {
                $error = self::shortError($e->getMessage());
                $chain[] = ['url' => $current, 'status' => null];
                break;
            }

            $status = $response->status();
            $chain[] = ['url' => $current, 'status' => $status];
            $location = $response->header('Location');

            if ($status >= 300 && $status < 400 && $location !== '') {
                if ($hop === $maxRedirects) {
                    $error = 'Too many redirects';
                    break;
                }
                $current = self::resolve($current, $location);

                continue;
            }

            break;
        }

        $elapsed = (int) round((hrtime(true) - $start) / 1_000_000);
        $finalUrl = end($chain)['url'] ?? $url;
        $tlsExpiresAt = null;
        if (str_starts_with(strtolower((string) $finalUrl), 'https://')) {
            $tlsExpiresAt = $this->tls->expiry((string) parse_url((string) $finalUrl, PHP_URL_HOST), (int) (parse_url((string) $finalUrl, PHP_URL_PORT) ?: 443));
        }

        return new ProbeResult(
            finalUrl: $finalUrl,
            statusCode: $error === null ? $status : null,
            chain: $chain,
            redirectCount: max(0, count($chain) - 1),
            responseMs: $elapsed,
            tlsExpiresAt: $tlsExpiresAt instanceof CarbonImmutable ? $tlsExpiresAt : null,
            error: $error,
        );
    }

    /** Resolves a (possibly relative) Location header against the current URL. */
    public static function resolve(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $parts = parse_url($base);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        if (str_starts_with($location, '//')) {
            return $scheme.':'.$location;
        }
        if (str_starts_with($location, '/')) {
            return $scheme.'://'.$host.$port.$location;
        }
        $path = $parts['path'] ?? '/';
        $dir = substr($path, 0, (int) strrpos($path, '/') + 1);

        return $scheme.'://'.$host.$port.$dir.$location;
    }

    private static function shortError(string $message): string
    {
        // cURL messages carry the whole URL and timing noise; keep the gist.
        if (preg_match('/cURL error (\d+): ([^(]+)/', $message, $m)) {
            return trim('cURL '.$m[1].': '.$m[2]);
        }

        return mb_substr($message, 0, 300);
    }
}
