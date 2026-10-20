<?php

namespace App\Jobs;

use App\Domain\Notifications\Notifier;
use App\Models\Run;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendDigests implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public ?int $runId = null) {}

    public function handle(Notifier $notifier): void
    {
        $sent = $notifier->digest();

        if ($this->runId && ($run = Run::query()->find($this->runId))) {
            $run->forceFill(['stats' => array_merge($run->stats ?? [], ['digests_sent' => $sent])])->save();
        }
    }
}
