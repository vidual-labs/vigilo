<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WebhookEndpoint extends Model
{
    protected $fillable = [
        'name', 'url', 'secret', 'send_digest', 'send_critical', 'is_active',
        'last_status', 'last_delivered_at', 'last_error',
    ];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'send_digest' => 'boolean',
            'send_critical' => 'boolean',
            'is_active' => 'boolean',
            'last_delivered_at' => 'datetime',
        ];
    }

    /** @param Builder<WebhookEndpoint> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
