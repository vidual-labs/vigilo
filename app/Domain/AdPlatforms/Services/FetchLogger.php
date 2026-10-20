<?php

namespace App\Domain\AdPlatforms\Services;

use App\Domain\AdPlatforms\Exceptions\AdPlatformException;
use App\Models\FetchLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Wraps one fetch operation and writes a fetch_logs row with its outcome,
 * duration and HTTP attempt count — every outbound fetch gets one.
 */
class FetchLogger
{
    /**
     * @template T
     *
     * @param  Closure(): T  $operation
     * @param  Closure(): int  $attempts  reads the adapter's attempt counter afterwards
     * @param  (Closure(T): ?int)|null  $countRows
     * @return T
     *
     * @throws Throwable
     */
    public function record(Model $subject, ?int $clientId, string $kind, Closure $operation, Closure $attempts, ?Closure $countRows = null): mixed
    {
        $startedAt = now();
        $start = hrtime(true);

        try {
            $result = $operation();
        } catch (Throwable $e) {
            $this->write($subject, $clientId, $kind, $startedAt, $start, [
                'status' => 'failed',
                'http_status' => $e instanceof AdPlatformException ? $e->httpStatus : null,
                'error_code' => $e instanceof AdPlatformException ? $e->errorCode : 'exception',
                'attempts' => max(1, $e instanceof AdPlatformException ? $e->attempts : $attempts()),
                'error' => mb_substr($e->getMessage(), 0, 2000),
            ]);

            throw $e;
        }

        $this->write($subject, $clientId, $kind, $startedAt, $start, [
            'status' => 'success',
            'attempts' => max(1, $attempts()),
            'rows' => $countRows ? $countRows($result) : null,
        ]);

        return $result;
    }

    /** @param array<string, mixed> $attributes */
    private function write(Model $subject, ?int $clientId, string $kind, \DateTimeInterface $startedAt, int|float $start, array $attributes): void
    {
        FetchLog::query()->create([
            'client_id' => $clientId,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'kind' => $kind,
            'duration_ms' => (int) round((hrtime(true) - $start) / 1_000_000),
            'started_at' => $startedAt,
            'finished_at' => now(),
            ...$attributes,
        ]);
    }
}
