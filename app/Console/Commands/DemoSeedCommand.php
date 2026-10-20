<?php

namespace App\Console\Commands;

use App\Domain\Demo\DemoSeeder;
use Illuminate\Console\Command;

class DemoSeedCommand extends Command
{
    protected $signature = 'vigilo:demo:seed {--first-boot : only seed once, only in mock mode, only when demo data is enabled}';

    protected $description = 'Load demo clients with simulated data (mock mode)';

    public function handle(DemoSeeder $seeder): int
    {
        if ($this->option('first-boot')) {
            if (! config('vigilo.mock_mode') || ! config('vigilo.demo_data') || DemoSeeder::alreadySeeded()) {
                return self::SUCCESS;
            }
        } elseif (! config('vigilo.mock_mode')) {
            $this->error('Demo data needs VIGILO_MOCK_MODE=true (it uses simulated connections).');

            return self::FAILURE;
        }

        $this->info('vigilo: loading demo clients…');
        $count = $seeder->seed();
        $this->info("vigilo: {$count} demo clients loaded. Remove them with `php artisan vigilo:demo:remove`.");

        return self::SUCCESS;
    }
}
