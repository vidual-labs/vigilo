<?php

namespace App\Domain\Checks\LandingPages;

use Carbon\CarbonImmutable;

/** Reads the expiry date of a host's TLS certificate (no HTTP request). */
class TlsInspector
{
    public function expiry(string $host, int $port = 443): ?CarbonImmutable
    {
        if ($host === '') {
            return null;
        }

        // Verification off on purpose: an expired certificate must still be
        // readable so we can report *when* it expired.
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ]]);

        $client = @stream_socket_client(
            'ssl://'.$host.':'.$port, $errno, $errstr,
            (float) config('vigilo.landing_pages.timeout', 10), STREAM_CLIENT_CONNECT, $context,
        );
        if ($client === false) {
            return null;
        }

        $params = stream_context_get_params($client);
        fclose($client);
        $certificate = $params['options']['ssl']['peer_certificate'] ?? null;
        if ($certificate === null) {
            return null;
        }
        $parsed = openssl_x509_parse($certificate);

        return isset($parsed['validTo_time_t']) ? CarbonImmutable::createFromTimestampUTC((int) $parsed['validTo_time_t']) : null;
    }
}
