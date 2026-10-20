<?php

namespace App\Models;

use App\Domain\Findings\Enums\FindingEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FindingEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['finding_id', 'type', 'user_id', 'data', 'created_at'];

    protected function casts(): array
    {
        return [
            'type' => FindingEventType::class,
            'data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Finding, $this> */
    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
