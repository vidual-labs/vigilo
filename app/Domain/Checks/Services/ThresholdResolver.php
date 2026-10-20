<?php

namespace App\Domain\Checks\Services;

use App\Domain\Checks\Contracts\Check;
use App\Domain\Findings\Enums\Severity;
use App\Models\CheckSetting;
use App\Models\Client;
use Illuminate\Support\Collection;

/**
 * Resolves a check's settings for a client: client override → install
 * override (both from check_settings) → config/vigilo.php default.
 */
class ThresholdResolver
{
    /** @var Collection<int, CheckSetting>|null */
    private ?Collection $globals = null;

    /** @return array{enabled: bool, values: array<string, mixed>} */
    public function resolve(Check $check, ?Client $client = null): array
    {
        $defaults = (array) config('vigilo.checks.'.$check->key(), []);
        $enabled = (bool) ($defaults['enabled'] ?? true);
        unset($defaults['enabled']);
        $values = $defaults;

        $layers = [$this->globals()->firstWhere('check_key', $check->key())];
        if ($client) {
            $layers[] = $client->relationLoaded('checkSettings')
                ? $client->checkSettings->firstWhere('check_key', $check->key())
                : CheckSetting::query()->where('client_id', $client->id)->where('check_key', $check->key())->first();
        }

        foreach (array_filter($layers) as $layer) {
            if ($layer->enabled !== null) {
                $enabled = $layer->enabled;
            }
            foreach ((array) ($layer->thresholds ?? []) as $name => $value) {
                if (array_key_exists($name, $check->fields()) && $value !== null && $value !== '') {
                    $values[$name] = self::cast($check->fields()[$name], $value);
                }
            }
        }

        return ['enabled' => $enabled, 'values' => $values];
    }

    public static function cast(string $type, mixed $value): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'float', 'ratio' => (float) $value,
            'severity' => Severity::tryFrom((string) $value)->value ?? Severity::Info->value,
            'int_list' => array_values(array_filter(array_map('intval', is_array($value) ? $value : explode(',', (string) $value)))),
            default => $value,
        };
    }

    /** Clears the per-instance cache of install-wide overrides. */
    public function flush(): void
    {
        $this->globals = null;
    }

    /** @return Collection<int, CheckSetting> */
    private function globals(): Collection
    {
        return $this->globals ??= CheckSetting::query()->whereNull('client_id')->get();
    }
}
