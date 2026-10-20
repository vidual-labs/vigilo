<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Used by the Docker entrypoint: block until the database accepts connections. */
class WaitForDatabaseCommand extends Command
{
    protected $signature = 'vigilo:db:wait {--timeout=90 : seconds}';

    protected $description = 'Wait until the database accepts connections';

    public function handle(): int
    {
        $deadline = time() + (int) $this->option('timeout');

        while (true) {
            try {
                DB::connection()->getPdo();
                $this->line('vigilo: database is ready.');

                return self::SUCCESS;
            } catch (Throwable $e) {
                if (time() >= $deadline) {
                    $this->error('vigilo: database not reachable: '.$e->getMessage());

                    return self::FAILURE;
                }
                DB::purge();
                sleep(2);
            }
        }
    }
}
