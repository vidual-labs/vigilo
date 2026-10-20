<?php

namespace App\Domain\Checks\Checks;

use App\Domain\AdPlatforms\Enums\ConnectionStatus;
use App\Domain\Checks\CheckResult;
use App\Domain\Checks\Snapshot\ClientSnapshot;
use App\Domain\Checks\Snapshot\ConnectionState;
use App\Domain\Checks\Snapshot\LeadSourceState;
use App\Domain\Findings\Enums\Severity;
use App\Support\Format;
use Carbon\CarbonImmutable;

/**
 * Is vigilo actually seeing the client's data? Failed authentication, no
 * successful fetch for too long, a Meta token about to expire, or an ad
 * account the platform has disabled. Covers the lead source too.
 */
class ConnectionHealthCheck extends BaseCheck
{
    public function key(): string
    {
        return 'connection_health';
    }

    public function fields(): array
    {
        return [
            'stale_hours' => 'int',
            'stale_critical_hours' => 'int',
            'token_warning_days' => 'int',
            'token_critical_days' => 'int',
        ];
    }

    public function run(ClientSnapshot $snapshot, array $thresholds): CheckResult
    {
        $result = new CheckResult;

        foreach ($snapshot->activeConnections() as $connection) {
            $this->fetchHealth($result, $snapshot, $thresholds, 'conn:'.$connection->id, $connection);
            $this->tokenHealth($result, $snapshot, $thresholds, $connection);

            if ($connection->accountActive === false && $connection->status !== ConnectionStatus::AuthFailed) {
                $result->add($this->draft($snapshot, 'conn:'.$connection->id.':account', Severity::Critical, 'account_inactive',
                    ['account' => $connection->name, 'status' => (string) $connection->accountStatus],
                    ['account' => $connection->name, 'account_status' => (string) $connection->accountStatus],
                    $connection->platform,
                ));
            }
        }

        if ($snapshot->leadSource !== null) {
            $this->fetchHealth($result, $snapshot, $thresholds, 'leads:'.$snapshot->leadSource->id, $snapshot->leadSource);
        }

        return $result;
    }

    /** @param array<string, mixed> $thresholds */
    private function fetchHealth(CheckResult $result, ClientSnapshot $snapshot, array $thresholds, string $subject, ConnectionState|LeadSourceState $state): void
    {
        $name = $state instanceof ConnectionState ? $state->name : Format::t('Lead source (:type)', ['type' => $state->type]);
        $platform = $state instanceof ConnectionState ? $state->platform : null;
        $evidence = [
            'account' => $name,
            'last_success_at' => self::time($snapshot, $state->lastSuccessAt),
            'last_attempt_at' => self::time($snapshot, $state->lastAttemptAt),
            'last_error' => $state->lastError ?? '—',
        ];

        if ($state->status === ConnectionStatus::AuthFailed) {
            $result->add($this->draft($snapshot, $subject, Severity::Critical, 'auth_failed', ['account' => $name], $evidence, $platform));

            return;
        }

        $reference = $state->lastSuccessAt ?? $state->createdAt;
        if ($reference === null) {
            return;
        }
        $hours = (int) floor($reference->diffInHours($snapshot->now, true));
        $severity = match (true) {
            $hours >= (int) $thresholds['stale_critical_hours'] => Severity::Critical,
            $hours >= (int) $thresholds['stale_hours'] => Severity::Warning,
            default => null,
        };
        if ($severity === null) {
            return;
        }

        $result->add($this->draft($snapshot, $subject, $severity,
            $state->lastSuccessAt ? 'stale' : 'never_fetched',
            ['account' => $name, 'hours' => $hours],
            $evidence,
            $platform,
        ));
    }

    /** @param array<string, mixed> $thresholds */
    private function tokenHealth(CheckResult $result, ClientSnapshot $snapshot, array $thresholds, ConnectionState $connection): void
    {
        if ($connection->tokenExpiresAt === null) {
            return;
        }

        $days = (int) floor($snapshot->now->diffInDays($connection->tokenExpiresAt, false));
        $expired = $connection->tokenExpiresAt->lessThanOrEqualTo($snapshot->now);
        $severity = match (true) {
            $expired, $days <= (int) $thresholds['token_critical_days'] => Severity::Critical,
            $days <= (int) $thresholds['token_warning_days'] => Severity::Warning,
            default => null,
        };
        if ($severity === null) {
            return;
        }

        $result->add($this->draft($snapshot, 'conn:'.$connection->id.':token', $severity,
            $expired ? 'token_expired' : 'token_expiring',
            ['account' => $connection->name, 'days' => max(0, $days)],
            ['account' => $connection->name, 'token_expires_at' => self::time($snapshot, $connection->tokenExpiresAt)],
            $connection->platform,
        ));
    }

    private static function time(ClientSnapshot $snapshot, ?CarbonImmutable $at): string
    {
        return $at ? $at->setTimezone($snapshot->now->getTimezone())->format('Y-m-d H:i') : '—';
    }
}
