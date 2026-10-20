<?php

namespace App\Models;

use App\Domain\Leads\Enums\LeadStatus;
use App\Enums\Platform;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lead metadata. Never add a column for names, contact details or answers:
 * the README's privacy section and CLAUDE.md promise self-hosters that vigilo
 * does not hold lead PII.
 */
class Lead extends Model
{
    protected $fillable = [
        'client_id', 'lead_source_id', 'external_id', 'submitted_at', 'status', 'status_changed_at',
        'source_type', 'meta_campaign_id', 'meta_adset_id', 'meta_ad_id', 'meta_form_id',
        'has_gclid', 'flags',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'status' => LeadStatus::class,
            'has_gclid' => 'boolean',
            'flags' => 'array',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Which ad platform the lead can be attributed to, if any. */
    public function attributedPlatform(): ?Platform
    {
        if ($this->meta_ad_id || $this->meta_adset_id || $this->meta_campaign_id || $this->source_type === 'meta_lead_ads') {
            return Platform::Meta;
        }

        return $this->has_gclid ? Platform::Google : null;
    }
}
