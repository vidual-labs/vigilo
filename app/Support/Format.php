<?php

namespace App\Support;

use App\Enums\Platform;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Number;

/**
 * Locale-aware formatting for finding titles and evidence.
 *
 * Checks store typed values (['type' => 'money', 'value' => 12.5, 'currency' =>
 * 'EUR']) instead of preformatted strings, so the same finding reads
 * "€1,234.50" for an English user and "1.234,50 €" for a German one.
 */
final class Format
{
    public static function money(float $value, string $currency = 'EUR', ?string $locale = null): string
    {
        return (string) Number::currency($value, $currency, self::intlLocale($locale));
    }

    public static function number(float|int $value, int $decimals = 0, ?string $locale = null): string
    {
        return (string) Number::format($value, $decimals, null, self::intlLocale($locale));
    }

    public static function percent(float $ratio, int $decimals = 0, ?string $locale = null): string
    {
        return (string) Number::percentage($ratio * 100, $decimals, null, self::intlLocale($locale));
    }

    public static function param(mixed $value, ?string $locale = null): string
    {
        if (! is_array($value)) {
            return (string) $value;
        }

        $raw = $value['value'] ?? '';

        return match ($value['type'] ?? null) {
            'money' => self::money((float) $raw, (string) ($value['currency'] ?? 'EUR'), $locale),
            'number' => self::number((float) $raw, (int) ($value['decimals'] ?? 0), $locale),
            'percent' => self::percent((float) $raw, (int) ($value['decimals'] ?? 0), $locale),
            'factor' => self::number((float) $raw, 1, $locale).'×',
            'platform' => Platform::tryFrom((string) $raw)?->label() ?? (string) $raw,
            // Translated when read, so the reader's language wins over the run's.
            'trans' => (string) __((string) $raw, array_map(fn ($p) => self::param($p, $locale), (array) ($value['params'] ?? [])), $locale),
            default => (string) $raw,
        };
    }

    /**
     * Typed param helpers used by checks.
     *
     * @return array<string, mixed>
     */
    public static function m(float $value, string $currency): array
    {
        return ['type' => 'money', 'value' => round($value, 2), 'currency' => $currency];
    }

    /** @return array<string, mixed> */
    public static function n(float|int $value, int $decimals = 0): array
    {
        return ['type' => 'number', 'value' => round((float) $value, $decimals), 'decimals' => $decimals];
    }

    /** @return array<string, mixed> */
    public static function p(float $ratio): array
    {
        return ['type' => 'percent', 'value' => round($ratio, 4)];
    }

    /** @return array<string, mixed> */
    public static function x(float $factor): array
    {
        return ['type' => 'factor', 'value' => round($factor, 2)];
    }

    /**
     * A string translated at render time.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function t(string $key, array $params = []): array
    {
        return ['type' => 'trans', 'value' => $key, 'params' => $params];
    }

    private static function intlLocale(?string $locale): string
    {
        return match ($locale ?? App::getLocale()) {
            'de' => 'de_DE',
            default => 'en_GB',
        };
    }
}
