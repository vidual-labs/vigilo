<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckSetting extends Model
{
    protected $fillable = ['client_id', 'check_key', 'enabled', 'thresholds'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'thresholds' => 'array',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
