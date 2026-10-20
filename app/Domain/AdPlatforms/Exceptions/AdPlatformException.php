<?php

namespace App\Domain\AdPlatforms\Exceptions;

use RuntimeException;

/**
 * Normalised failure from an ad platform or lead source. The error code drives
 * connection status and the connection-health check, so keep the set small.
 */
class AdPlatformException extends RuntimeException
{
    public const AUTH = 'auth';

    public const RATE_LIMITED = 'rate_limited';

    public const CONFIG = 'config';

    public const NOT_FOUND = 'not_found';

    public const SERVER = 'server';

    public const NETWORK = 'network';

    public const INVALID_RESPONSE = 'invalid_response';

    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly ?int $httpStatus = null,
        public readonly bool $retryable = false,
        public readonly ?int $retryAfterSeconds = null,
        public int $attempts = 1,
    ) {
        parent::__construct($message);
    }

    public function isAuth(): bool
    {
        return $this->errorCode === self::AUTH;
    }

    public static function config(string $message): self
    {
        return new self($message, self::CONFIG);
    }
}
