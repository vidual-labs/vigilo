<?php

namespace App\Console\Commands;

use App\Domain\Demo\DemoSeeder;
use Illuminate\Console\Command;

class DemoRemoveCommand extends Command
{
    protected $signature = 'vigilo:demo:remove';

    protected $description = 'Delete the demo clients and everything attached to them';

    public function handle(DemoSeeder $seeder): int
    {
        $this->info('vigilo: removed '.$seeder->remove().' demo clients.');

        return self::SUCCESS;
    }
}
