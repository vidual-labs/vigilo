<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrlProbe extends Model
{
    protected $fillable = [
        'client_id', 'url', 'url_hash', 'origin', 'final_url', 'status_code', 'redirect_chain',
        'redirect_count', 'response_ms', 'tls_expires_at', 'error', 'consecutive_failures', 'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'redirect_chain' => 'array',
            'tls_expires_at' => 'datetime',
            'checked_at' => 'datetime',
            'status_code' => 'integer',
            'redirect_count' => 'integer',
            'response_ms' => 'integer',
            'consecutive_failures' => 'integer',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
