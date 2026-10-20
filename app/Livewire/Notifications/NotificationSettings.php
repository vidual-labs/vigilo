<?php

namespace App\Livewire\Notifications;

use App\Domain\Notifications\WebhookDispatcher;
use App\Jobs\DeliverWebhook;
use App\Jobs\SendDigests;
use App\Livewire\Concerns\ChecksOperator;
use App\Models\WebhookEndpoint;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Component;
use Throwable;

class NotificationSettings extends Component
{
    use ChecksOperator;

    public string $name = '';

    public string $url = '';

    public bool $sendDigest = true;

    public bool $sendCritical = true;

    /** Shown once after creating an endpoint or rotating its secret. */
    public ?string $revealedSecret = null;

    public ?int $revealedFor = null;

    /** @var array<int, string> */
    public array $testResults = [];

    public function addEndpoint(): void
    {
        $this->authorizeOperator();
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url:https,http', 'max:2048'],
        ]);
        $secret = Str::random(48);
        $endpoint = WebhookEndpoint::query()->create([
            'name' => $this->name,
            'url' => $this->url,
            'secret' => $secret,
            'send_digest' => $this->sendDigest,
            'send_critical' => $this->sendCritical,
        ]);
        $this->reset('name', 'url');
        $this->revealedSecret = $secret;
        $this->revealedFor = $endpoint->id;
    }

    public function toggle(int $id, string $field): void
    {
        $this->authorizeOperator();
        abort_unless(in_array($field, ['is_active', 'send_digest', 'send_critical'], true), 400);
        $endpoint = WebhookEndpoint::query()->findOrFail($id);
        $endpoint->update([$field => ! $endpoint->{$field}]);
    }

    public function rotate(int $id): void
    {
        $this->authorizeOperator();
        $secret = Str::random(48);
        WebhookEndpoint::query()->findOrFail($id)->update(['secret' => $secret]);
        $this->revealedSecret = $secret;
        $this->revealedFor = $id;
    }

    public function delete(int $id): void
    {
        $this->authorizeOperator();
        WebhookEndpoint::query()->findOrFail($id)->delete();
    }

    public function sendTest(int $id): void
    {
        $this->authorizeOperator();
        $body = WebhookDispatcher::body('ping', ['text' => 'vigilo test message — the webhook works.']);
        try {
            DeliverWebhook::dispatchSync($id, $body);
            $this->testResults[$id] = __('Delivered (HTTP :status).', ['status' => WebhookEndpoint::query()->find($id)?->last_status]);
        } catch (Throwable $e) {
            $this->testResults[$id] = __('Failed: :error', ['error' => Str::limit($e->getMessage(), 200)]);
        }
    }

    public function sendDigestNow(): void
    {
        $this->authorizeOperator();
        SendDigests::dispatch();
        session()->flash('status', __('Digest queued for everyone who receives it.'));
    }

    public function render(): View
    {
        return view('livewire.notifications.settings', [
            'endpoints' => WebhookEndpoint::query()->orderBy('name')->get(),
            'mailer' => config('mail.default'),
            'from' => config('mail.from.address'),
        ])->title(__('Notifications'));
    }
}
