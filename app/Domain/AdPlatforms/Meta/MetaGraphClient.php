<?php

namespace App\Domain\AdPlatforms\Meta;

use App\Domain\AdPlatforms\Exceptions\AdPlatformException;
use App\Domain\AdPlatforms\Http\ResilientHttp;
use App\Models\AdConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * Minimal Graph API client for the Marketing API (version pinned in
 * config/vigilo.php). GET only — vigilo never writes to Meta.
 */
class MetaGraphClient
{
    /** Throttling error codes (app, user, account, ads insights/management). */
    private const RATE_LIMIT_CODES = [4, 17, 32, 613, 80000, 80001, 80002, 80003, 80004, 80005, 80006, 80008, 80009, 80014];

    /** Permission/token error codes. */
    private const AUTH_CODES = [102, 190, 200, 10, 294, 2635];

    public function __construct(private readonly ResilientHttp $http) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws AdPlatformException
     */
    public function get(AdConnection $connection, string $path, array $query = []): array
    {
        $token = $this->token($connection);
        $url = $this->url($path);
        $query = $this->withAuth($token, $query);

        $response = $this->http->send(
            fn (PendingRequest $request) => $request->get($url, $query),
            fn (Response $response) => self::classify($response),
        );

        $json = $response->json();
        if (! is_array($json)) {
            throw new AdPlatformException('Meta returned a non-JSON response.', AdPlatformException::INVALID_RESPONSE);
        }

        return $json;
    }

    /**
     * Follows `paging.cursors.after` until exhausted.
     *
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     *
     * @throws AdPlatformException
     */
    public function getAll(AdConnection $connection, string $path, array $query = [], int $maxPages = 50): array
    {
        $rows = [];
        $pages = 0;
        do {
            $json = $this->get($connection, $path, $query);
            foreach ((array) ($json['data'] ?? []) as $row) {
                $rows[] = (array) $row;
            }
            $after = $json['paging']['cursors']['after'] ?? null;
            $hasNext = isset($json['paging']['next']);
            $query['after'] = $after;
        } while ($hasNext && $after && ++$pages < $maxPages);

        return $rows;
    }

    /** @throws AdPlatformException */
    public function token(AdConnection $connection): string
    {
        $token = $connection->credential('access_token') ?? (string) config('vigilo.meta.access_token');
        if ($token === '') {
            throw AdPlatformException::config('Meta access token is not configured: set VIGILO_META_ADS_ACCESS_TOKEN or a per-connection token.');
        }

        return $token;
    }

    private function url(string $path): string
    {
        return sprintf('%s/%s/%s', rtrim((string) config('vigilo.meta.base_url'), '/'), config('vigilo.meta.api_version'), ltrim($path, '/'));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function withAuth(string $token, array $query): array
    {
        $query['access_token'] = $token;
        $secret = (string) config('vigilo.meta.app_secret');
        if ($secret !== '') {
            $query['appsecret_proof'] = hash_hmac('sha256', $token, $secret);
        }

        return $query;
    }

    public static function classify(Response $response): AdPlatformException
    {
        $status = $response->status();
        $error = (array) $response->json('error', []);
        $code = (int) ($error['code'] ?? 0);
        $type = (string) ($error['type'] ?? '');
        $message = 'Meta API '.$status.': '.($error['message'] ?? $response->reason()).($code ? " (code {$code})" : '');

        if (in_array($code, self::RATE_LIMIT_CODES, true) || $status === 429) {
            return new AdPlatformException(
                $message, AdPlatformException::RATE_LIMITED, $status, retryable: true,
                retryAfterSeconds: self::regainAccessSeconds($response) ?? ResilientHttp::retryAfter($response),
            );
        }

        if (in_array($code, self::AUTH_CODES, true) || $type === 'OAuthException' || $status === 401) {
            return new AdPlatformException($message, AdPlatformException::AUTH, $status);
        }

        if ($status >= 500 || in_array($code, [1, 2], true)) {
            return new AdPlatformException($message, AdPlatformException::SERVER, $status, retryable: true);
        }

        if ($status === 404 || $code === 803) {
            return new AdPlatformException($message, AdPlatformException::NOT_FOUND, $status);
        }

        return new AdPlatformException($message, AdPlatformException::CONFIG, $status);
    }

    /** Meta's own wait hint, from the business-use-case / ad-account usage headers (minutes). */
    private static function regainAccessSeconds(Response $response): ?int
    {
        $minutes = null;
        foreach (['X-Business-Use-Case-Usage', 'X-Ad-Account-Usage'] as $header) {
            $decoded = json_decode((string) $response->header($header), true);
            if (! is_array($decoded)) {
                continue;
            }
            array_walk_recursive($decoded, function ($value, $key) use (&$minutes) {
                if (in_array($key, ['estimated_time_to_regain_access', 'reset_time_duration'], true) && is_numeric($value)) {
                    $minutes = max((int) $minutes, (int) $value);
                }
            });
        }

        return $minutes === null ? null : $minutes * 60;
    }
}
