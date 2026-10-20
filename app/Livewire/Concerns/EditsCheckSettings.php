<?php

namespace App\Livewire\Concerns;

use App\Domain\Checks\CheckRegistry;
use App\Domain\Checks\Services\ThresholdResolver;
use App\Domain\Findings\Enums\Severity;
use App\Models\CheckSetting;
use App\Models\Client;
use Illuminate\Validation\ValidationException;

/**
 * The check-settings form, shared by /checks (install-wide, client = null)
 * and the client settings page (per-client overrides). Empty field = inherit.
 */
trait EditsCheckSettings
{
    /** @var array<string, array{enabled: string, values: array<string, string>}> */
    public array $checkForm = [];

    protected function loadCheckForm(?Client $client): void
    {
        $settings = CheckSetting::query()
            ->when($client, fn ($q) => $q->where('client_id', $client?->id), fn ($q) => $q->whereNull('client_id'))
            ->get()->keyBy('check_key');

        foreach (app(CheckRegistry::class)->all() as $key => $check) {
            $setting = $settings->get($key);
            $values = [];
            foreach ($check->fields() as $field => $type) {
                $value = $setting?->thresholds[$field] ?? null;
                $values[$field] = is_array($value) ? implode(', ', $value) : (string) ($value ?? '');
            }
            $this->checkForm[$key] = [
                'enabled' => $setting?->enabled === null ? '' : ($setting->enabled ? '1' : '0'),
                'values' => $values,
            ];
        }
    }

    protected function saveCheckForm(?Client $client): void
    {
        $errors = [];
        foreach (app(CheckRegistry::class)->all() as $key => $check) {
            $form = $this->checkForm[$key] ?? ['enabled' => '', 'values' => []];
            $thresholds = [];
            foreach ($check->fields() as $field => $type) {
                $raw = trim((string) ($form['values'][$field] ?? ''));
                if ($raw === '') {
                    continue;
                }
                $raw = str_replace(',', $type === 'int_list' ? ',' : '.', $raw);
                $valid = match ($type) {
                    'int' => preg_match('/^\d+$/', $raw) === 1,
                    'float' => is_numeric($raw) && (float) $raw >= 0,
                    'ratio' => is_numeric($raw) && (float) $raw >= 0 && (float) $raw <= 10,
                    'severity' => Severity::tryFrom($raw) !== null,
                    'int_list' => preg_match('/^\s*\d+(\s*,\s*\d+)*\s*$/', $raw) === 1,
                    default => true,
                };
                if (! $valid) {
                    $errors["checkForm.{$key}.values.{$field}"] = __('Invalid value.');

                    continue;
                }
                $thresholds[$field] = ThresholdResolver::cast($type, $raw);
            }

            $enabled = match ($form['enabled']) {
                '1' => true,
                '0' => false,
                default => null,
            };

            if ($errors !== []) {
                continue;
            }

            if ($enabled === null && $thresholds === []) {
                CheckSetting::query()->where('check_key', $key)
                    ->when($client, fn ($q) => $q->where('client_id', $client?->id), fn ($q) => $q->whereNull('client_id'))
                    ->delete();

                continue;
            }

            CheckSetting::query()->updateOrCreate(
                ['client_id' => $client?->id, 'check_key' => $key],
                ['enabled' => $enabled, 'thresholds' => $thresholds],
            );
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * What applies when a field is left empty (shown as placeholder).
     *
     * @return array<string, array{enabled: bool, values: array<string, mixed>}>
     */
    protected function inheritedCheckSettings(?Client $client): array
    {
        $resolver = new ThresholdResolver;
        $inherited = [];
        foreach (app(CheckRegistry::class)->all() as $key => $check) {
            // For a client, "inherit" means the install-wide value; for the
            // install, it means the config default.
            $inherited[$key] = $client
                ? $resolver->resolve($check)
                : ['enabled' => (bool) config("vigilo.checks.{$key}.enabled", true), 'values' => array_diff_key((array) config("vigilo.checks.{$key}", []), ['enabled' => 1])];
        }

        return $inherited;
    }
}
