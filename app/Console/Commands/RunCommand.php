<?php

namespace App\Console\Commands;

use App\Domain\Runs\Enums\RunTrigger;
use App\Domain\Runs\RunManager;
use App\Models\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class RunCommand extends Command
{
    protected $signature = 'vigilo:run
        {--client= : only this client id}
        {--trigger=cli : schedule or cli}
        {--no-fetch : run checks on the data already stored}
        {--digest : send digests afterwards (default only for the scheduled run)}
        {--sync : run inline instead of on the queue}';

    protected $description = 'Fetch data, run all checks and (for the scheduled run) send digests';

    public function handle(RunManager $runs): int
    {
        $client = null;
        if ($this->option('client')) {
            $client = Client::query()->find($this->option('client'));
            if ($client === null) {
                $this->error('No such client.');

                return self::FAILURE;
            }
        }

        if ($this->option('sync')) {
            Config::set('queue.default', 'sync');
        }

        $trigger = RunTrigger::tryFrom((string) $this->option('trigger')) ?? RunTrigger::Cli;
        $run = $runs->start(
            $trigger,
            $client,
            sendDigest: $this->option('digest') ? true : null,
            fetch: ! $this->option('no-fetch'),
        );

        $this->info("vigilo: run #{$run->id} ".($this->option('sync') ? 'finished: '.$run->status->value : 'queued').'.');
        if ($run->stats) {
            $this->line(json_encode($run->stats) ?: '');
        }

        return self::SUCCESS;
    }
}
