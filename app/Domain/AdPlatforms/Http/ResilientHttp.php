<?php

namespace App\Domain\AdPlatforms\Http;

use App\Domain\AdPlatforms\Exceptions\AdPlatformException;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Outbound HTTP with retries for the ad platform and lead source adapters.
 *
 * Retries network errors and whatever the adapter's classifier marks as
 * retryable (rate limits, 5xx) with exponential backoff, honouring
 * Retry-After / platform wait hints up to `max_wait_seconds`. Waits go
 * through Sleep so tests can fake them.
 */
final class ResilientHttp
{
    private int $attempts = 0;

    public function __construct(
        private readonly int $retries,
        private readonly int $backoffMs,
        private readonly int $maxWaitSeconds,
        private readonly int $timeout,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            retries: max(0, (int) config('vigilo.http.retries', 3)),
            backoffMs: max(0, (int) config('vigilo.http.backoff_ms', 1000)),
            maxWaitSeconds: max(1, (int) config('vigilo.http.max_wait_seconds', 60)),
            timeout: max(1, (int) config('vigilo.http.timeout', 30)),
        );
    }

    /**
     * @param  Closure(PendingRequest): Response  $send
     * @param  Closure(Response): AdPlatformException  $classify  turns an unsuccessful response into an exception
     *
     * @throws AdPlatformException
     */
    public function send(Closure $send, Closure $classify): Response
    {
        $maxAttempts = $this->retries + 1;

        for ($attempt = 1; ; $attempt++) {
            $this->attempts++;

            try {
                $response = $send($this->request());
            } catch (ConnectionException $e) {
                if ($attempt < $maxAttempts) {
                    $this->wait($attempt, null);

                    continue;
                }

                throw new AdPlatformException(
                    'Network error: '.$e->getMessage(), AdPlatformException::NETWORK, attempts: $attempt,
                );
            }

            if ($response->successful()) {
                return $response;
            }

            $error = $classify($response);
            $error->attempts = $attempt;

            $hint = $error->retryAfterSeconds;
            if ($error->retryable && $attempt < $maxAttempts && ($hint === null || $hint <= $this->maxWaitSeconds)) {
                $this->wait($attempt, $hint);

                continue;
            }

            throw $error;
        }
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function resetAttempts(): void
    {
        $this->attempts = 0;
    }

    public static function retryAfter(Response $response): ?int
    {
        $header = $response->header('Retry-After');

        return is_numeric($header) ? (int) $header : null;
    }

    private function request(): PendingRequest
    {
        return Http::timeout($this->timeout)
            ->connectTimeout(min(10, $this->timeout))
            ->acceptJson()
            ->withUserAgent('vigilo/'.config('vigilo.version'));
    }

    private function wait(int $attempt, ?int $hintSeconds): void
    {
        $ms = $hintSeconds !== null
            ? $hintSeconds * 1000
            : $this->backoffMs * (3 ** ($attempt - 1));

        Sleep::for(min($ms, $this->maxWaitSeconds * 1000))->milliseconds();
    }
}
