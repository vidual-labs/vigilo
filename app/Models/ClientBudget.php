<?php

namespace App\Models;

use App\Enums\Platform;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientBudget extends Model
{
    protected $fillable = ['client_id', 'platform', 'monthly_amount'];

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'monthly_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
