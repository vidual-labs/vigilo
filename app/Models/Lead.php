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
        'has_gclid', 'google_campaign_id', 'google_ad_group_id', 'google_criterion_id', 'flags',
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

        return $this->has_gclid || $this->google_campaign_id ? Platform::Google : null;
    }

    /**
     * Whether the lead counts towards lead totals and cost per lead: not a
     * duplicate (by status or by lodgely's duplicate flag) and not incomplete.
     * lodgely's reporting counts the same way (ROADMAP → Shared with vigilo,
     * decision V6).
     */
    public function isCountable(): bool
    {
        return $this->status->isCountable() && empty($this->flags['duplicate']);
    }
}
