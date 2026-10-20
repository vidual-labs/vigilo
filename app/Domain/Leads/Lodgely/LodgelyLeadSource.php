<?php

namespace App\Domain\Leads\Lodgely;

use App\Domain\AdPlatforms\Exceptions\AdPlatformException;
use App\Domain\AdPlatforms\Http\ResilientHttp;
use App\Domain\Leads\Contracts\LeadSource;
use App\Domain\Leads\Data\LeadMetadata;
use App\Domain\Leads\Data\LeadPage;
use App\Models\LeadSource as LeadSourceModel;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * Client for lodgely's lead-metadata read API, contract v1
 * (docs/LODGELY-CONTRACT.md). lodgely does not ship this API yet, so the
 * adapter sits behind VIGILO_LODGELY_ENABLED and is covered by contract tests
 * against fixtures in tests/Fixtures/lodgely.
 */
class LodgelyLeadSource implements LeadSource
{
    public const CONTRACT_VERSION = 1;

    public function __construct(private readonly ResilientHttp $http) {}

    public function fetch(LeadSourceModel $source, ?string $cursor): LeadPage
    {
        $query = [
            'client' => $this->clientKey($source),
            'limit' => (int) config('vigilo.lodgely.page_size', 500),
        ];
        if ($cursor) {
            $query['cursor'] = $cursor;
        } else {
            $query['updated_since'] = now()->subDays((int) config('vigilo.lodgely.initial_lookback_days', 60))->utc()->toIso8601String();
        }

        $json = $this->get('/api/v1/lead-metadata', $query);

        $leads = [];
        $deleted = [];
        foreach ((array) ($json['data'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (($row['deleted'] ?? false) === true) {
                if (isset($row['id']) && is_scalar($row['id'])) {
                    $deleted[] = (string) $row['id'];
                }

                continue;
            }
            try {
                $leads[] = LeadMetadata::fromContract($row);
            } catch (\Throwable) {
                // A malformed row must not block the feed; it is skipped.
            }
        }

        return new LeadPage(
            leads: $leads,
            nextCursor: isset($json['next_cursor']) && is_string($json['next_cursor']) ? $json['next_cursor'] : $cursor,
            hasMore: (bool) ($json['has_more'] ?? false),
            deletedIds: $deleted,
        );
    }

    public function test(LeadSourceModel $source): string
    {
        $json = $this->get('/api/v1/lead-metadata', ['client' => $this->clientKey($source), 'limit' => 1]);

        return __('lodgely answered (contract v:version).', ['version' => $json['contract_version'] ?? '?']);
    }

    public function attempts(): int
    {
        return $this->http->attempts();
    }

    public function resetAttempts(): void
    {
        $this->http->resetAttempts();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws AdPlatformException
     */
    private function get(string $path, array $query): array
    {
        if (! config('vigilo.lodgely.enabled')) {
            throw AdPlatformException::config('The lodgely integration is disabled (VIGILO_LODGELY_ENABLED=false).');
        }
        $base = (string) config('vigilo.lodgely.url');
        $token = (string) config('vigilo.lodgely.token');
        if ($base === '' || $token === '') {
            throw AdPlatformException::config('Set VIGILO_LODGELY_URL and VIGILO_LODGELY_API_TOKEN to use lodgely.');
        }

        $response = $this->http->send(
            fn (PendingRequest $request) => $request->withToken($token)
                ->withHeaders(['X-Vigilo-Contract' => (string) self::CONTRACT_VERSION])
                ->get($base.$path, $query),
            fn (Response $response) => self::classify($response),
        );

        $json = $response->json();
        if (! is_array($json)) {
            throw new AdPlatformException('lodgely returned a non-JSON response.', AdPlatformException::INVALID_RESPONSE);
        }
        $version = (int) ($json['contract_version'] ?? 0);
        if ($version !== self::CONTRACT_VERSION) {
            throw new AdPlatformException(
                "lodgely speaks contract version {$version}, vigilo expects ".self::CONTRACT_VERSION.'.',
                AdPlatformException::INVALID_RESPONSE,
            );
        }

        return $json;
    }

    /** @throws AdPlatformException */
    private function clientKey(LeadSourceModel $source): string
    {
        $key = trim((string) $source->external_key);
        if ($key === '') {
            throw AdPlatformException::config('No lodgely client key is set for this client.');
        }

        return $key;
    }

    public static function classify(Response $response): AdPlatformException
    {
        $status = $response->status();
        $message = 'lodgely '.$status.': '.($response->json('message') ?? $response->reason());

        return match (true) {
            $status === 401, $status === 403 => new AdPlatformException($message, AdPlatformException::AUTH, $status),
            $status === 404 => new AdPlatformException($message, AdPlatformException::NOT_FOUND, $status),
            $status === 429 => new AdPlatformException($message, AdPlatformException::RATE_LIMITED, $status, true, ResilientHttp::retryAfter($response)),
            $status >= 500 => new AdPlatformException($message, AdPlatformException::SERVER, $status, true),
            default => new AdPlatformException($message, AdPlatformException::CONFIG, $status),
        };
    }
}
