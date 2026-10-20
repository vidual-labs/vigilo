<?php

namespace App\Domain\Leads\Enums;

/**
 * Lead status as delivered by the lead source contract. The values mirror
 * lodgely's LeadStatus so a lodgely lead maps 1:1 (docs/LODGELY-CONTRACT.md).
 * Anything vigilo does not know becomes Unknown instead of failing the fetch.
 */
enum LeadStatus: string
{
    case New = 'new';
    case Reviewed = 'reviewed';
    case Incomplete = 'incomplete';
    case Duplicate = 'duplicate';
    case Pending = 'pending';
    case OfferSent = 'offer_sent';
    case Successful = 'successful';
    case Declined = 'declined';
    case NoReply = 'no_reply';
    case Forwarded = 'forwarded';
    case Unknown = 'unknown';

    public static function fromContract(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Unknown;
    }

    /**
     * Whether the lead counts towards lead volume and cost per lead.
     * Duplicates and partial submissions do not.
     */
    public function isCountable(): bool
    {
        return ! in_array($this, [self::Duplicate, self::Incomplete], true);
    }

    /** Outcome bucket used by the Phase 2 lead-quality miner. */
    public function outcome(): string
    {
        return match ($this) {
            self::Successful => 'won',
            self::Declined, self::NoReply => 'lost',
            self::Duplicate, self::Incomplete => 'junk',
            self::Pending, self::OfferSent, self::Forwarded => 'in_progress',
            default => 'open',
        };
    }
}
