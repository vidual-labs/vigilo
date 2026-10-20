<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FetchLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'client_id', 'subject_type', 'subject_id', 'kind', 'status', 'http_status',
        'error_code', 'attempts', 'rows', 'duration_ms', 'error', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
