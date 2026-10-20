<?php

namespace App\Domain\Notifications;

use App\Domain\Findings\Enums\FindingEventType;
use App\Domain\Findings\Services\FindingReconciler;
use App\Mail\CriticalFindingsMail;
use App\Mail\DailyDigestMail;
use App\Models\Finding;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class Notifier
{
    public function __construct(
        private readonly DigestBuilder $digests,
        private readonly WebhookDispatcher $webhooks,
        private readonly FindingReconciler $reconciler,
    ) {}

    /**
     * New or escalated critical findings. Webhooks fire right away; mail
     * only when $mail is true (outside the scheduled run — there the digest
     * follows within minutes and would repeat the same news).
     *
     * @param  list<Finding>  $findings
     */
    public function critical(array $findings, bool $mail): int
    {
        $due = array_values(array_filter($findings, fn (Finding $f) => FindingReconciler::needsImmediateAlert($f)));
        if ($due === []) {
            return 0;
        }

        foreach ($due as $finding) {
            $finding->forceFill(['notified_at' => now(), 'notified_severity' => $finding->severity])->save();
            $this->reconciler->event($finding, FindingEventType::Notified, data: ['mail' => $mail]);
        }

        if ($mail && config('vigilo.notifications.critical_immediately')) {
            $ids = array_map(fn (Finding $f) => $f->id, $due);
            foreach (User::query()->active()->where('notify_critical', true)->get() as $user) {
                $this->send($user, new CriticalFindingsMail($user, $ids));
            }
        }

        foreach ($due as $finding) {
            $payload = FindingPayload::make($finding);
            $this->webhooks->dispatch('finding.critical', [
                'text' => sprintf('[critical] %s: %s', $payload['client']['name'], $payload['title']),
                'finding' => $payload,
            ], 'critical');
        }

        return count($due);
    }

    /** Sends the daily digest to everyone who wants it. Returns mails sent. */
    public function digest(): int
    {
        $sent = 0;
        $since = CarbonImmutable::now()->subDay();
        $sendEmpty = (bool) config('vigilo.notifications.digest_when_empty');

        foreach (User::query()->active()->where('receives_digest', true)->get() as $user) {
            $digest = $this->digests->build($user, $since);
            if (! $sendEmpty && array_sum($digest['counts']) === 0) {
                continue;
            }
            if ($this->send($user, new DailyDigestMail($user, $digest))) {
                $sent++;
            }
        }

        $this->digestWebhook($since);

        return $sent;
    }

    private function digestWebhook(CarbonImmutable $since): void
    {
        $findings = Finding::query()->active()->where('status', '!=', 'snoozed')->with('client')->orderBySeverity()->limit(200)->get();
        $counts = ['critical' => 0, 'warning' => 0, 'info' => 0];
        foreach ($findings as $finding) {
            $counts[$finding->severity->value]++;
        }

        $this->webhooks->dispatch('digest', [
            'text' => sprintf('vigilo digest: %d critical, %d warning, %d info — %s',
                $counts['critical'], $counts['warning'], $counts['info'], route('findings.index')),
            'summary' => $counts + ['new' => $findings->filter(fn (Finding $f) => $f->isNewSince($since))->count()],
            'findings' => $findings->map(fn (Finding $f) => FindingPayload::make($f))->all(),
        ], 'digest');
    }

    private function send(User $user, Mailable $mail): bool
    {
        try {
            Mail::to($user)->locale($user->preferredLocale())->send($mail);

            return true;
        } catch (Throwable $e) {
            Log::error('vigilo: could not send mail to user '.$user->id.': '.$e->getMessage());

            return false;
        }
    }
}
