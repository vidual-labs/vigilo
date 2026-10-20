<?php

namespace App\Console\Commands;

use App\Domain\Notifications\Notifier;
use Illuminate\Console\Command;

class DigestCommand extends Command
{
    protected $signature = 'vigilo:digest';

    protected $description = 'Send the daily digest now (to every user who receives it)';

    public function handle(Notifier $notifier): int
    {
        $this->info('vigilo: sent '.$notifier->digest().' digest(s).');

        return self::SUCCESS;
    }
}
