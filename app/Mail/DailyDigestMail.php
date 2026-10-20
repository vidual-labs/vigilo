<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class DailyDigestMail extends Mailable
{
    use Queueable;

    /** @param array<string, mixed> $digest from DigestBuilder::build() */
    public function __construct(public User $user, public array $digest) {}

    public function envelope(): Envelope
    {
        $counts = $this->digest['counts'];

        return new Envelope(subject: array_sum($counts) === 0
            ? __('vigilo digest: all clear')
            : __('vigilo digest: :critical critical, :warning warnings', ['critical' => $counts['critical'], 'warning' => $counts['warning']]));
    }

    public function content(): Content
    {
        return new Content(html: 'mail.digest', text: 'mail.digest-text');
    }
}
