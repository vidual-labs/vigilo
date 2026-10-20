<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * `php artisan db:seed` = first operator + demo data (mock mode). The Docker
 * entrypoint runs the same two commands itself on every start.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('vigilo:admin:ensure', [], $this->command->getOutput());
        if (config('vigilo.mock_mode')) {
            Artisan::call('vigilo:demo:seed', [], $this->command->getOutput());
        }
    }
}
