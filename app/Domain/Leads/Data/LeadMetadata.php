<?php

namespace App\Domain\Leads\Data;

use App\Domain\Leads\Enums\LeadStatus;
use Carbon\CarbonImmutable;

/**
 * The complete set of lead fields vigilo will ever hold. It is an
 * ALLOW-LIST: fromContract() reads only the keys below and ignores
 * everything else in the payload, so a source that sends names, emails,
 * phone numbers or answers by mistake still never gets them stored.
 */
final readonly class LeadMetadata
{
    /** Boolean enrichment flags accepted from a source. */
    public const FLAGS = ['duplicate', 'qualified', 'contacted', 'organic', 'spam'];

    /** @param array<string, bool> $flags */
    public function __construct(
        public string $externalId,
        public CarbonImmutable $submittedAt,
        public LeadStatus $status,
        public ?CarbonImmutable $statusChangedAt = null,
        public ?string $sourceType = null,
        public ?string $metaCampaignId = null,
        public ?string $metaAdsetId = null,
        public ?string $metaAdId = null,
        public ?string $metaFormId = null,
        public bool $hasGclid = false,
        public array $flags = [],
    ) {}

    /**
     * Maps one lead object of the lodgely contract (docs/LODGELY-CONTRACT.md).
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromContract(array $row): self
    {
        $attribution = is_array($row['attribution'] ?? null) ? $row['attribution'] : [];
        $flags = [];
        foreach (self::FLAGS as $flag) {
            if (isset($row['flags'][$flag])) {
                $flags[$flag] = (bool) $row['flags'][$flag];
            }
        }

        return new self(
            externalId: self::id($row['id'] ?? null) ?? throw new \InvalidArgumentException('Lead without id'),
            submittedAt: CarbonImmutable::parse((string) ($row['submitted_at'] ?? throw new \InvalidArgumentException('Lead without submitted_at')))->utc(),
            status: LeadStatus::fromContract(is_string($row['status'] ?? null) ? $row['status'] : null),
            statusChangedAt: isset($row['status_changed_at']) ? CarbonImmutable::parse((string) $row['status_changed_at'])->utc() : null,
            sourceType: self::token($row['source_type'] ?? null),
            metaCampaignId: self::id($attribution['meta_campaign_id'] ?? null),
            metaAdsetId: self::id($attribution['meta_adset_id'] ?? null),
            metaAdId: self::id($attribution['meta_ad_id'] ?? null),
            metaFormId: self::id($attribution['meta_form_id'] ?? null),
            hasGclid: (bool) ($attribution['has_gclid'] ?? false),
            flags: $flags,
        );
    }

    /** @return array<string, mixed> columns for the leads table */
    public function toRow(): array
    {
        return [
            'external_id' => $this->externalId,
            'submitted_at' => $this->submittedAt,
            'status' => $this->status->value,
            'status_changed_at' => $this->statusChangedAt,
            'source_type' => $this->sourceType,
            'meta_campaign_id' => $this->metaCampaignId,
            'meta_adset_id' => $this->metaAdsetId,
            'meta_ad_id' => $this->metaAdId,
            'meta_form_id' => $this->metaFormId,
            'has_gclid' => $this->hasGclid,
            'flags' => $this->flags,
        ];
    }

    /** Ids are short opaque tokens; anything else (e.g. an email) is rejected. */
    private static function id(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $value = (string) $value;

        return preg_match('/^[A-Za-z0-9_\-:.]{1,128}$/', $value) ? $value : null;
    }

    private static function token(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[a-z0-9_]{1,40}$/', $value) ? $value : null;
    }
}
