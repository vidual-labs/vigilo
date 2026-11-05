<?php

namespace App\Domain\Checks\Checks;

use App\Domain\Checks\CheckResult;
use App\Domain\Checks\Snapshot\ClientSnapshot;
use App\Domain\Checks\Snapshot\ProbeState;
use App\Domain\Findings\Enums\Severity;
use App\Support\Format;

/**
 * Landing pages of active ads plus the client's monitored URLs, as probed
 * by LandingPageProbeService (plain GET, never a form submission):
 * unreachable or error status, long or off-site redirect chains, HTTPS
 * downgrades, slow responses, and TLS certificates close to expiry.
 */
class LandingPageCheck extends BaseCheck
{
    /**
     * Probes older than this are not judged. The probe service drops URLs no
     * longer in use, so an old probe means probing did not run: its findings
     * are kept, not resolved.
     */
    private const MAX_PROBE_AGE_HOURS = 48;

    public function key(): string
    {
        return 'landing_page';
    }

    public function fields(): array
    {
        return [
            'slow_ms' => 'int',
            'max_redirects' => 'int',
            'tls_warning_days' => 'int',
            'tls_critical_days' => 'int',
            'failures_before_alert' => 'int',
        ];
    }

    public function run(ClientSnapshot $snapshot, array $thresholds): CheckResult
    {
        $result = new CheckResult;
        $tlsHosts = [];
        // Hosts with a page that could not be judged: their certificate
        // finding stays unless another page of the host tells us more.
        $tlsUnknown = [];

        foreach ($snapshot->probes as $probe) {
            $subject = 'url:'.$probe->urlHash;
            $host = (string) parse_url($probe->finalUrl ?: $probe->url, PHP_URL_HOST);

            if ($probe->checkedAt === null || $probe->checkedAt->diffInHours($snapshot->now, true) > self::MAX_PROBE_AGE_HOURS) {
                $result->keep($this->subjectKey($snapshot, $subject.':'));
                $tlsUnknown[$host] = true;

                continue;
            }
            $params = ['url' => self::short($probe->url)];
            $evidence = $this->evidence($snapshot, $probe);

            if ($probe->failed()) {
                // A page that did not load says nothing about its redirects,
                // speed or certificate: keep those findings as they are.
                $result->keep($this->subjectKey($snapshot, $subject.':'));
                if ($probe->tlsExpiresAt === null) {
                    $tlsUnknown[$host] = true;
                }
                // One failure may be a blip; alert from the configured count on.
                if ($probe->consecutiveFailures >= max(1, (int) $thresholds['failures_before_alert'])) {
                    $result->add($this->draft($snapshot, $subject.':down', Severity::Critical, 'down',
                        $params + ['status' => $probe->statusCode ? (string) $probe->statusCode : Format::t('no response')], $evidence));
                }
            } else {
                if ($probe->redirectCount > (int) $thresholds['max_redirects']) {
                    $result->add($this->draft($snapshot, $subject.':redirects', Severity::Warning, 'redirects',
                        $params + ['count' => $probe->redirectCount], $evidence));
                }
                if ($probe->finalUrl && self::site($probe->finalUrl) !== self::site($probe->url)) {
                    $result->add($this->draft($snapshot, $subject.':offsite', Severity::Warning, 'offsite',
                        $params + ['target' => self::short($probe->finalUrl)], $evidence));
                }
                if ($probe->finalUrl && str_starts_with($probe->url, 'https://') && str_starts_with($probe->finalUrl, 'http://')) {
                    $result->add($this->draft($snapshot, $subject.':insecure', Severity::Warning, 'insecure', $params, $evidence));
                }
                if ($probe->responseMs !== null && $probe->responseMs > (int) $thresholds['slow_ms']) {
                    $result->add($this->draft($snapshot, $subject.':slow', Severity::Warning, 'slow',
                        $params + ['seconds' => Format::n($probe->responseMs / 1000, 1)], $evidence));
                }
            }

            if ($probe->tlsExpiresAt !== null && $host !== '' && ! isset($tlsHosts[$host])) {
                $tlsHosts[$host] = true;
                $this->tls($result, $snapshot, $thresholds, $host, $probe);
            }
        }

        foreach (array_keys(array_diff_key($tlsUnknown, $tlsHosts)) as $host) {
            if ($host !== '') {
                $result->keep($this->subjectKey($snapshot, 'tls:'.$host));
            }
        }

        return $result;
    }

    /** @param array<string, mixed> $thresholds */
    private function tls(CheckResult $result, ClientSnapshot $snapshot, array $thresholds, string $host, ProbeState $probe): void
    {
        $expires = $probe->tlsExpiresAt;
        if ($expires === null) {
            return;
        }
        $days = (int) floor($snapshot->now->diffInDays($expires, false));
        $expired = $expires->lessThanOrEqualTo($snapshot->now);
        $severity = match (true) {
            $expired, $days <= (int) $thresholds['tls_critical_days'] => Severity::Critical,
            $days <= (int) $thresholds['tls_warning_days'] => Severity::Warning,
            default => null,
        };
        if ($severity === null) {
            return;
        }

        $result->add($this->draft($snapshot, 'tls:'.$host, $severity, $expired ? 'tls_expired' : 'tls_expiring',
            ['host' => $host, 'days' => max(0, $days)],
            ['host' => $host, 'certificate_expires_at' => $expires->setTimezone($snapshot->now->getTimezone())->format('Y-m-d H:i')],
        ));
    }

    /** @return array<string, mixed> */
    private function evidence(ClientSnapshot $snapshot, ProbeState $probe): array
    {
        return [
            'url' => $probe->url,
            'origin' => Format::t('evidence.origin_'.$probe->origin),
            'final_url' => $probe->finalUrl ?? '—',
            'status_code' => $probe->statusCode ?? '—',
            'error' => $probe->error ?? '—',
            'response_time' => $probe->responseMs !== null ? Format::n($probe->responseMs) : '—',
            'redirect_chain' => array_map(fn (array $hop) => ['url' => $hop['url'], 'status' => (string) ($hop['status'] ?? '—')], $probe->redirectChain),
            'failures_in_a_row' => $probe->consecutiveFailures,
            'checked_at' => $probe->checkedAt?->setTimezone($snapshot->now->getTimezone())->format('Y-m-d H:i') ?? '—',
        ];
    }

    /** Registrable-ish site: host without "www." (good enough for "did we leave the site"). */
    private static function site(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $parts = explode('.', preg_replace('/^www\./', '', $host) ?? $host);

        return implode('.', array_slice($parts, -2));
    }

    private static function short(string $url): string
    {
        $short = preg_replace('#^https?://(www\.)?#i', '', $url) ?? $url;

        return mb_strlen($short) > 70 ? mb_substr($short, 0, 67).'…' : $short;
    }
}
