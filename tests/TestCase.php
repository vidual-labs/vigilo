<?php

namespace Tests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * Safety net: the Feature tests drop every table (RefreshDatabase), so
     * they must never reach a real database — e.g. when `php artisan test`
     * runs inside a production container whose environment says
     * DB_CONNECTION=pgsql. Checked right after boot, before any trait runs.
     */
    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException("Refusing to run tests against database [{$connection}:{$database}]; tests only use in-memory SQLite.");
        }

        return $app;
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
