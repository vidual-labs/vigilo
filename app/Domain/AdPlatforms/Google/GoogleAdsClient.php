<?php

namespace App\Domain\AdPlatforms\Google;

use App\Domain\AdPlatforms\Exceptions\AdPlatformException;
use App\Domain\AdPlatforms\Http\ResilientHttp;
use App\Models\AdConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Minimal Google Ads REST client: OAuth refresh → access token, then
 * `googleAds:search` with GAQL and page tokens. Read-only by construction —
 * there is no mutate call in here and there must never be one.
 */
class GoogleAdsClient
{
    public function __construct(private readonly ResilientHttp $http) {}

    /**
     * Runs a GAQL query and returns every row across all pages.
     *
     * @return list<array<string, mixed>>
     *
     * @throws AdPlatformException
     */
    public function search(AdConnection $connection, string $gaql): array
    {
        $customerId = preg_replace('/\D/', '', $connection->account_id) ?? '';
        if ($customerId === '') {
            throw AdPlatformException::config('Google Ads customer id is empty.');
        }

        $url = sprintf(
            '%s/%s/customers/%s/googleAds:search',
            rtrim((string) config('vigilo.google_ads.base_url'), '/'),
            config('vigilo.google_ads.api_version'),
            $customerId,
        );
        $headers = $this->headers($connection);

        $rows = [];
        $pageToken = null;
        $pages = 0;
        do {
            $body = ['query' => $gaql];
            if ($pageToken) {
                $body['pageToken'] = $pageToken;
            }

            $response = $this->http->send(
                fn (PendingRequest $request) => $request->withHeaders($headers)->post($url, $body),
                fn (Response $response) => self::classify($response),
            );

            $json = $response->json();
            if (! is_array($json)) {
                throw new AdPlatformException('Google Ads returned a non-JSON response.', AdPlatformException::INVALID_RESPONSE);
            }
            foreach ($json['results'] ?? [] as $row) {
                $rows[] = $row;
            }
            $pageToken = $json['nextPageToken'] ?? null;
        } while ($pageToken && ++$pages < 100);

        return $rows;
    }

    /**
     * @return array<string, string>
     *
     * @throws AdPlatformException
     */
    private function headers(AdConnection $connection): array
    {
        $developerToken = (string) config('vigilo.google_ads.developer_token');
        if ($developerToken === '') {
            throw AdPlatformException::config('VIGILO_GOOGLE_ADS_DEVELOPER_TOKEN is not set.');
        }

        $headers = [
            'Authorization' => 'Bearer '.$this->accessToken($connection),
            'developer-token' => $developerToken,
        ];

        $login = preg_replace('/\D/', '', (string) ($connection->login_customer_id ?: config('vigilo.google_ads.login_customer_id')));
        if ($login) {
            $headers['login-customer-id'] = $login;
        }

        return $headers;
    }

    /** @throws AdPlatformException */
    private function accessToken(AdConnection $connection): string
    {
        $refreshToken = $connection->credential('refresh_token') ?? (string) config('vigilo.google_ads.refresh_token');
        $clientId = (string) config('vigilo.google_ads.client_id');
        $clientSecret = (string) config('vigilo.google_ads.client_secret');

        if ($refreshToken === '' || $clientId === '' || $clientSecret === '') {
            throw AdPlatformException::config(
                'Google Ads OAuth is not configured: set VIGILO_GOOGLE_ADS_CLIENT_ID, VIGILO_GOOGLE_ADS_CLIENT_SECRET and a refresh token.'
            );
        }

        $cacheKey = 'vigilo:google-access-token:'.sha1($clientId.'|'.$refreshToken);
        $cached = Cache::get($cacheKey);
        if (is_string($cached)) {
            try {
                return Crypt::decryptString($cached);
            } catch (\Throwable) {
                Cache::forget($cacheKey);
            }
        }

        $response = $this->http->send(
            fn (PendingRequest $request) => $request->asForm()->post((string) config('vigilo.google_ads.token_url'), [
                'grant_type' => 'refresh_token',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken,
            ]),
            fn (Response $response) => self::classifyOauth($response),
        );

        $token = (string) $response->json('access_token', '');
        if ($token === '') {
            throw new AdPlatformException('Google OAuth returned no access token.', AdPlatformException::INVALID_RESPONSE);
        }

        $ttl = max(60, (int) $response->json('expires_in', 3600) - 120);
        Cache::put($cacheKey, Crypt::encryptString($token), $ttl);

        return $token;
    }

    public static function classify(Response $response): AdPlatformException
    {
        $status = $response->status();
        $error = (array) $response->json('error', []);
        $grpcStatus = (string) ($error['status'] ?? '');
        $message = (string) ($error['message'] ?? $response->reason());

        // The interesting reason (DEVELOPER_TOKEN_NOT_APPROVED, …) sits in details.
        foreach ((array) ($error['details'] ?? []) as $detail) {
            foreach ((array) ($detail['errors'] ?? []) as $e) {
                $code = is_array($e['errorCode'] ?? null) ? implode(',', array_map('strval', $e['errorCode'])) : '';
                $message = trim(($code !== '' ? $code.': ' : '').($e['message'] ?? $message));
                break 2;
            }
        }

        $message = 'Google Ads API '.$status.': '.$message;

        return match (true) {
            $status === 401, $grpcStatus === 'UNAUTHENTICATED',
            $status === 403, $grpcStatus === 'PERMISSION_DENIED' => new AdPlatformException($message, AdPlatformException::AUTH, $status),
            $status === 429, $grpcStatus === 'RESOURCE_EXHAUSTED' => new AdPlatformException(
                $message, AdPlatformException::RATE_LIMITED, $status, retryable: true, retryAfterSeconds: ResilientHttp::retryAfter($response),
            ),
            $status === 404 => new AdPlatformException($message, AdPlatformException::NOT_FOUND, $status),
            $status >= 500 => new AdPlatformException($message, AdPlatformException::SERVER, $status, retryable: true),
            default => new AdPlatformException($message, AdPlatformException::CONFIG, $status),
        };
    }

    public static function classifyOauth(Response $response): AdPlatformException
    {
        $status = $response->status();
        $error = (string) $response->json('error', '');
        $description = (string) $response->json('error_description', $response->reason());
        $message = 'Google OAuth '.$status.': '.trim($error.' '.$description);

        if ($status >= 500) {
            return new AdPlatformException($message, AdPlatformException::SERVER, $status, retryable: true);
        }
        if ($status === 429) {
            return new AdPlatformException($message, AdPlatformException::RATE_LIMITED, $status, retryable: true);
        }

        // invalid_grant = refresh token revoked/expired; invalid_client = wrong id/secret.
        return new AdPlatformException($message, AdPlatformException::AUTH, $status);
    }
}
