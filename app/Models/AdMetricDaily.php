<?php

namespace App\Models;

use App\Enums\Platform;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdMetricDaily extends Model
{
    protected $table = 'ad_metrics_daily';

    protected $fillable = [
        'ad_connection_id', 'client_id', 'platform', 'date', 'campaign_id', 'campaign_name',
        'spend', 'impressions', 'clicks', 'conversions',
    ];

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'date' => 'date',
            'spend' => 'float',
            'conversions' => 'float',
            'impressions' => 'integer',
            'clicks' => 'integer',
        ];
    }

    /** @return BelongsTo<AdConnection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(AdConnection::class, 'ad_connection_id');
    }
}
