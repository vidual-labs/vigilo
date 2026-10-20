<?php

namespace Tests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function operator(array $attributes = []): User
    {
        return User::factory()->create(['role' => UserRole::Operator, ...$attributes]);
    }

    protected function viewer(array $attributes = []): User
    {
        return User::factory()->create(['role' => UserRole::Viewer, ...$attributes]);
    }
}
