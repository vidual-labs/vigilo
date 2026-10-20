<?php

namespace App\Domain\AdPlatforms\Mock;

/** Deterministic pseudo-randomness: same inputs, same numbers, every run. */
final class Seeded
{
    /** Float in [0, 1). */
    public static function float(string ...$parts): float
    {
        return hexdec(substr(md5(implode('|', $parts)), 0, 8)) / 0x100000000;
    }

    /** Multiplier in [1 - spread, 1 + spread]. */
    public static function jitter(float $spread, string ...$parts): float
    {
        return 1 - $spread + 2 * $spread * self::float(...$parts);
    }
}
