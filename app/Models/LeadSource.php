<?php

namespace App\Models;

use App\Domain\AdPlatforms\Enums\ConnectionStatus;
use App\Domain\Leads\Enums\LeadSourceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property LeadSourceType $type
 * @property ConnectionStatus $status
 * @property array<string, mixed>|null $settings
 */
class LeadSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'type', 'external_key', 'settings', 'is_active', 'cursor', 'status',
        'last_attempt_at', 'last_success_at', 'last_error_code', 'last_error', 'consecutive_failures',
    ];

    protected function casts(): array
    {
        return [
            'type' => LeadSourceType::class,
            'status' => ConnectionStatus::class,
            'settings' => 'array',
            'is_active' => 'boolean',
            'last_attempt_at' => 'datetime',
            'last_success_at' => 'datetime',
            'consecutive_failures' => 'integer',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return HasMany<Lead, $this> */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /** @return MorphMany<FetchLog, $this> */
    public function fetchLogs(): MorphMany
    {
        return $this->morphMany(FetchLog::class, 'subject');
    }

    public function mockScenario(): string
    {
        return (string) (($this->settings ?? [])['mock_scenario'] ?? 'healthy');
    }
}
