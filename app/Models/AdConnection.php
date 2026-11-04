<?php

namespace App\Models;

use App\Domain\AdPlatforms\Enums\ConnectionStatus;
use App\Enums\Platform;
use Database\Factories\AdConnectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property Platform $platform
 * @property ConnectionStatus $status
 * @property array<string, string>|null $credentials
 * @property array<string, mixed>|null $settings
 */
class AdConnection extends Model
{
    /** @use HasFactory<AdConnectionFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id', 'platform', 'name', 'account_id', 'login_customer_id',
        'credentials', 'settings', 'is_active', 'status', 'last_attempt_at',
        'last_success_at', 'last_error_code', 'last_error', 'consecutive_failures',
        'token_expires_at', 'token_checked_at',
    ];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'status' => ConnectionStatus::class,
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'is_active' => 'boolean',
            'last_attempt_at' => 'datetime',
            'last_success_at' => 'datetime',
            'token_expires_at' => 'datetime',
            'token_checked_at' => 'datetime',
            'consecutive_failures' => 'integer',
        ];
    }

    /** @param Builder<AdConnection> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return HasMany<AdMetricDaily, $this> */
    public function metrics(): HasMany
    {
        return $this->hasMany(AdMetricDaily::class);
    }

    /** @return HasMany<Ad, $this> */
    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }

    /** @return MorphMany<FetchLog, $this> */
    public function fetchLogs(): MorphMany
    {
        return $this->morphMany(FetchLog::class, 'subject');
    }

    /** Per-connection token override, if one was saved. */
    public function credential(string $key): ?string
    {
        $value = ($this->credentials ?? [])[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function hasCredentialOverride(): bool
    {
        return ! empty(array_filter($this->credentials ?? []));
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return ($this->settings ?? [])[$key] ?? $default;
    }

    public function mockScenario(): string
    {
        return (string) $this->setting('mock_scenario', 'healthy');
    }

    /**
     * Meta action types counted as conversions, in order of preference. A
     * connection may override the install default
     * (VIGILO_META_CONVERSION_ACTION_TYPES), e.g. for an account optimising
     * for a custom conversion (offsite_conversion.custom.<id>).
     *
     * @return list<string>
     */
    public function metaConversionActionTypes(): array
    {
        $own = array_values(array_filter((array) $this->setting('meta_conversion_action_types', [])));

        return $own !== [] ? $own : array_values((array) config('vigilo.meta.conversion_action_types', ['lead']));
    }

    /**
     * Parse a comma-separated list of Meta action types. Only Meta's own
     * token characters survive (letters, digits, dot, underscore).
     *
     * @return list<string>
     */
    public static function parseActionTypes(string $raw): array
    {
        $types = array_map('trim', explode(',', $raw));

        return array_values(array_unique(array_filter($types, fn (string $t) => preg_match('/^[a-z0-9_.]{1,100}$/i', $t) === 1)));
    }

    public function displayName(): string
    {
        return $this->name ?: ($this->setting('account_name') ?: $this->platform->label().' '.$this->displayAccountId());
    }

    /** Google ids are shown hyphenated (123-456-7890) like in the Ads UI. */
    public function displayAccountId(): string
    {
        if ($this->platform === Platform::Google && preg_match('/^\d{10}$/', $this->account_id)) {
            return substr($this->account_id, 0, 3).'-'.substr($this->account_id, 3, 3).'-'.substr($this->account_id, 6);
        }

        return $this->account_id;
    }

    /** Normalises what an operator pastes into the account id field. */
    public static function normalizeAccountId(Platform $platform, string $raw): string
    {
        $raw = trim($raw);

        return match ($platform) {
            Platform::Google => preg_replace('/\D/', '', $raw) ?? '',
            Platform::Meta => 'act_'.(preg_replace('/\D/', '', $raw) ?? ''),
        };
    }
}
