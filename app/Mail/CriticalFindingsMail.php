<?php

namespace App\Mail;

use App\Models\Finding;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CriticalFindingsMail extends Mailable
{
    use Queueable;

    /** @param list<int> $findingIds */
    public function __construct(public User $user, public array $findingIds) {}

    /** @return Collection<int, Finding> */
    public function findings(): Collection
    {
        return Finding::query()->visibleTo($this->user)->whereIn('id', $this->findingIds)->with('client')->get();
    }

    public function envelope(): Envelope
    {
        $findings = $this->findings();

        return new Envelope(subject: $findings->count() === 1
            ? __('Critical: :client – :title', ['client' => $findings->first()?->client?->name, 'title' => $findings->first()?->title()])
            : __(':count new critical findings', ['count' => $findings->count()]));
    }

    public function content(): Content
    {
        return new Content(html: 'mail.critical', text: 'mail.critical-text', with: ['findings' => $this->findings()]);
    }
}
