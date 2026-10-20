<?php

use App\Domain\Leads\Data\LeadMetadata;
use App\Domain\Leads\Enums\LeadStatus;

it('keeps only allow-listed metadata, whatever the source sends', function () {
    $lead = LeadMetadata::fromContract([
        'id' => 'lead_01HZ',
        'submitted_at' => '2026-10-19T08:15:00+02:00',
        'status' => 'offer_sent',
        'source_type' => 'meta_lead_ads',
        'attribution' => ['meta_ad_id' => '2385', 'has_gclid' => false, 'gclid' => 'Cj0KCQ-should-not-survive'],
        'flags' => ['duplicate' => false, 'qualified' => true, 'favourite_colour' => true],
        // Things that must never be stored:
        'full_name' => 'Maria Schulz',
        'email' => 'maria@example.com',
        'phone' => '+49 170 1234567',
        'answers' => ['message' => 'Bitte rufen Sie mich an'],
    ]);

    $row = $lead->toRow();
    $serialized = json_encode($row);

    expect($row)->toHaveKeys(['external_id', 'submitted_at', 'status', 'meta_ad_id', 'has_gclid', 'flags'])
        ->and($lead->status)->toBe(LeadStatus::OfferSent)
        ->and($lead->submittedAt->toIso8601String())->toBe('2026-10-19T06:15:00+00:00')
        ->and($lead->flags)->toBe(['duplicate' => false, 'qualified' => true])
        ->and($serialized)->not->toContain('Maria')
        ->and($serialized)->not->toContain('maria@')
        ->and($serialized)->not->toContain('1234567')
        ->and($serialized)->not->toContain('anrufen')
        ->and($serialized)->not->toContain('Cj0KCQ');
});

it('rejects ids that are not opaque tokens', function () {
    $lead = LeadMetadata::fromContract([
        'id' => 'x1', 'submitted_at' => '2026-10-19T08:15:00Z',
        'attribution' => ['meta_campaign_id' => 'maria@example.com'],
    ]);

    expect($lead->metaCampaignId)->toBeNull()->and($lead->status)->toBe(LeadStatus::Unknown);
});

it('maps statuses to countability and outcome buckets', function () {
    expect(LeadStatus::Duplicate->isCountable())->toBeFalse()
        ->and(LeadStatus::Incomplete->outcome())->toBe('junk')
        ->and(LeadStatus::Successful->outcome())->toBe('won')
        ->and(LeadStatus::fromContract('something_new'))->toBe(LeadStatus::Unknown);
});
