<?php

namespace App\Models;

use App\Domain\Findings\Enums\FindingStatus;
use App\Domain\Findings\Enums\Severity;
use App\Enums\Platform;
use App\Support\Format;
use Database\Factories\FindingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property Severity $severity
 * @property FindingStatus $status
 * @property Platform|null $platform
 * @property array<string, mixed>|null $title_params
 * @property array<string, mixed>|null $evidence
 */
class Finding extends Model
{
    /** @use HasFactory<FindingFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id', 'check_key', 'dedupe_key', 'platform', 'severity', 'status',
        'title_key', 'title_params', 'evidence', 'first_detected_at', 'last_detected_at',
        'opened_at', 'acknowledged_at', 'acknowledged_by', 'snoozed_until', 'resolved_at',
        'resolved_by', 'notified_at', 'notified_severity', 'occurrences', 'reopen_count',
    ];

    protected function casts(): array
    {
        return [
            'severity' => Severity::class,
            'status' => FindingStatus::class,
            'platform' => Platform::class,
            'notified_severity' => Severity::class,
            'title_params' => 'array',
            'evidence' => 'array',
            'first_detected_at' => 'datetime',
            'last_detected_at' => 'datetime',
            'opened_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'snoozed_until' => 'datetime',
            'resolved_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return HasMany<FindingEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(FindingEvent::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    /** @return BelongsTo<User, $this> */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /** @return BelongsTo<User, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Findings follow client visibility; see Client::scopeVisibleTo().
     *
     * @param  Builder<Finding>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereHas('client', fn (Builder $q) => $q->visibleTo($user));
    }

    /** @param Builder<Finding> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', FindingStatus::activeValues());
    }

    /** @param Builder<Finding> $query */
    public function scopeOrderBySeverity(Builder $query): void
    {
        $query->orderByRaw("case severity when 'critical' then 0 when 'warning' then 1 else 2 end");
    }

    /** Title in the current locale, with numbers/money formatted for it. */
    public function title(?string $locale = null): string
    {
        $params = [];
        foreach ($this->title_params ?? [] as $name => $value) {
            $params[$name] = Format::param($value, $locale);
        }

        // Plural-aware strings ("{1} …|[2,*] …") pick their form from the count.
        $line = __($this->title_key, [], $locale);
        if (is_string($line) && str_contains($line, '|')) {
            $raw = $this->title_params ?? [];
            $number = $raw['count'] ?? $raw['days'] ?? $raw['hours'] ?? 1;

            return trans_choice($this->title_key, (int) (is_array($number) ? ($number['value'] ?? 1) : $number), $params, $locale);
        }

        return __($this->title_key, $params, $locale);
    }

    public function checkLabel(?string $locale = null): string
    {
        return __('checks.'.$this->check_key.'.name', [], $locale);
    }

    public function isNewSince(\DateTimeInterface $since): bool
    {
        return $this->opened_at->greaterThanOrEqualTo($since);
    }
}
