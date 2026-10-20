<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('correct-horse-battery-9'),
            'role' => UserRole::Viewer,
            'locale' => 'en',
            'is_active' => true,
            'receives_digest' => true,
            'notify_critical' => true,
            'remember_token' => Str::random(10),
        ];
    }
}
