<?php

namespace App\Models;

use App\Domain\AdPlatforms\Enums\ReviewStatus;
use App\Enums\Platform;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ad extends Model
{
    protected $fillable = [
        'ad_connection_id', 'client_id', 'platform', 'external_id', 'name',
        'campaign_id', 'campaign_name', 'ad_group_id', 'ad_group_name', 'is_active',
        'delivery_status', 'review_status', 'review_status_raw', 'policy_topics',
        'final_urls', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'review_status' => ReviewStatus::class,
            'is_active' => 'boolean',
            'policy_topics' => 'array',
            'final_urls' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AdConnection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(AdConnection::class, 'ad_connection_id');
    }
}
