<?php

namespace Database\Factories;

use App\Domain\Findings\Enums\FindingStatus;
use App\Domain\Findings\Enums\Severity;
use App\Models\Client;
use App\Models\Finding;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Finding> */
class FindingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'check_key' => 'landing_page',
            'dedupe_key' => 'landing_page:'.Str::random(12),
            'severity' => Severity::Warning,
            'status' => FindingStatus::Open,
            'title_key' => 'findings.landing_page.slow',
            'title_params' => ['url' => 'example.com/', 'seconds' => '4.2'],
            'evidence' => ['url' => 'https://example.com/'],
            'first_detected_at' => now(),
            'last_detected_at' => now(),
            'opened_at' => now(),
        ];
    }

    public function critical(): static
    {
        return $this->state(fn () => ['severity' => Severity::Critical]);
    }
}
