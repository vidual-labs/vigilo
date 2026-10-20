<?php

namespace App\Models;

use App\Domain\Runs\Enums\RunStatus;
use App\Domain\Runs\Enums\RunTrigger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Run extends Model
{
    protected $fillable = [
        'trigger', 'client_id', 'user_id', 'status', 'batch_id', 'send_digest',
        'stats', 'error', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'trigger' => RunTrigger::class,
            'status' => RunStatus::class,
            'send_digest' => 'boolean',
            'stats' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
