<?php

namespace Database\Factories;

use App\Enums\Platform;
use App\Models\AdConnection;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AdConnection> */
class AdConnectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'platform' => Platform::Google,
            'account_id' => (string) fake()->unique()->numberBetween(1000000000, 9999999999),
            'settings' => ['mock_scenario' => 'healthy'],
        ];
    }

    public function meta(): static
    {
        return $this->state(fn () => [
            'platform' => Platform::Meta,
            'account_id' => 'act_'.fake()->unique()->numberBetween(100000000, 999999999),
        ]);
    }

    public function scenario(string $scenario): static
    {
        return $this->state(fn () => ['settings' => ['mock_scenario' => $scenario]]);
    }
}
