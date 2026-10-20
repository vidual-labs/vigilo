<?php

namespace App\Models;

use App\Enums\Platform;
use Carbon\CarbonImmutable;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug', 'timezone', 'currency', 'is_active', 'is_demo', 'notes'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    /**
     * The one place that decides which clients a user may see. v1 has only
     * operators and viewers, who both see every client. Client-portal logins
     * (later phase) restrict this — every user-facing query must go through it.
     *
     * @param  Builder<Client>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        // Intentionally unrestricted in v1.
    }

    /** @param Builder<Client> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @return HasMany<AdConnection, $this> */
    public function connections(): HasMany
    {
        return $this->hasMany(AdConnection::class);
    }

    /** @return HasOne<LeadSource, $this> */
    public function leadSource(): HasOne
    {
        return $this->hasOne(LeadSource::class);
    }

    /** @return HasMany<ClientBudget, $this> */
    public function budgets(): HasMany
    {
        return $this->hasMany(ClientBudget::class);
    }

    /** @return HasMany<MonitoredUrl, $this> */
    public function monitoredUrls(): HasMany
    {
        return $this->hasMany(MonitoredUrl::class);
    }

    /** @return HasMany<Finding, $this> */
    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    /** @return HasMany<CheckSetting, $this> */
    public function checkSettings(): HasMany
    {
        return $this->hasMany(CheckSetting::class);
    }

    public function budgetFor(Platform $platform): ?float
    {
        $budget = $this->budgets->firstWhere('platform', $platform->value);

        return $budget ? (float) $budget->monthly_amount : null;
    }

    /** "Now" in the client's own timezone — the basis for every day window. */
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone ?: config('vigilo.timezone'));
    }
}
