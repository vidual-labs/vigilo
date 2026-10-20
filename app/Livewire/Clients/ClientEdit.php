<?php

namespace App\Livewire\Clients;

use App\Domain\AdPlatforms\Mock\MockScenarios;
use App\Domain\Checks\CheckRegistry;
use App\Domain\Leads\Enums\LeadSourceType;
use App\Domain\Leads\LeadSourceRegistry;
use App\Domain\Leads\Services\LeadSync;
use App\Domain\Runs\Enums\RunTrigger;
use App\Domain\Runs\RunManager;
use App\Enums\Platform;
use App\Jobs\SyncLeadSourceNow;
use App\Livewire\Concerns\ChecksOperator;
use App\Livewire\Concerns\EditsCheckSettings;
use App\Models\Client;
use App\Models\MonitoredUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ClientEdit extends Component
{
    use ChecksOperator, EditsCheckSettings;

    public const CURRENCIES = ['EUR', 'CHF', 'USD', 'GBP', 'PLN', 'CZK', 'SEK', 'DKK', 'NOK'];

    public ?Client $client = null;

    public string $name = '';

    public string $timezone = '';

    public string $currency = 'EUR';

    public bool $isActive = true;

    public string $notes = '';

    /** @var array<string, string> platform => amount */
    public array $budgets = ['google' => '', 'meta' => ''];

    public string $leadType = '';

    public string $leadKey = '';

    public bool $leadActive = true;

    public string $leadScenario = 'healthy';

    public string $newUrl = '';

    public string $newUrlLabel = '';

    public ?string $leadTestResult = null;

    public bool $leadTestOk = false;

    public function mount(?Client $client = null): void
    {
        if ($client?->exists) {
            $this->client = Client::query()->visibleTo(auth()->user())->findOrFail($client->id);
            $this->fill([
                'name' => $client->name,
                'timezone' => $client->timezone,
                'currency' => $client->currency,
                'isActive' => $client->is_active,
                'notes' => (string) $client->notes,
            ]);
            foreach ($client->budgets as $budget) {
                $this->budgets[$budget->platform->value] = (string) (float) $budget->monthly_amount;
            }
            if ($source = $client->leadSource) {
                $this->leadType = $source->type->value;
                $this->leadKey = (string) $source->external_key;
                $this->leadActive = $source->is_active;
                $this->leadScenario = $source->mockScenario();
            }
        } else {
            $this->timezone = (string) config('vigilo.timezone');
        }
        $this->loadCheckForm($this->client);
    }

    public function save(): void
    {
        $this->authorizeOperator();
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'timezone:all'],
            'currency' => ['required', Rule::in(self::CURRENCIES)],
            'isActive' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'budgets.*' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'leadType' => ['nullable', Rule::in(array_map(fn ($t) => $t->value, LeadSourceType::cases()))],
            'leadKey' => ['nullable', 'required_if:leadType,lodgely', 'string', 'max:191', 'regex:/^[A-Za-z0-9_\-.:]+$/'],
            'leadScenario' => ['nullable', Rule::in(MockScenarios::LEAD_SCENARIOS)],
        ]);

        $attributes = [
            'name' => $data['name'],
            'timezone' => $data['timezone'],
            'currency' => $data['currency'],
            'is_active' => $this->isActive,
            'notes' => $data['notes'] ?: null,
        ];

        if ($this->client) {
            $this->client->update($attributes);
        } else {
            $this->client = Client::query()->create($attributes + ['slug' => $this->uniqueSlug($data['name'])]);
        }

        foreach (Platform::cases() as $platform) {
            $amount = trim((string) ($this->budgets[$platform->value] ?? ''));
            if ($amount === '' || (float) $amount <= 0) {
                $this->client->budgets()->where('platform', $platform->value)->delete();
            } else {
                $this->client->budgets()->updateOrCreate(['platform' => $platform->value], ['monthly_amount' => (float) $amount]);
            }
        }

        $this->saveLeadSource();
        $this->saveCheckForm($this->client);

        session()->flash('status', __('Client saved.'));
        $this->redirectRoute('clients.edit', $this->client, navigate: true);
    }

    private function saveLeadSource(): void
    {
        $source = $this->client?->leadSource;
        if ($this->leadType === '') {
            $source?->delete();

            return;
        }

        $attributes = [
            'type' => $this->leadType,
            'external_key' => $this->leadKey !== '' ? $this->leadKey : null,
            'is_active' => $this->leadActive,
            'settings' => array_merge($source->settings ?? [], ['mock_scenario' => $this->leadScenario ?: 'healthy']),
        ];
        if ($source && ($source->type->value !== $this->leadType || $source->external_key !== $attributes['external_key'])) {
            // Different feed: start over, and drop leads from the old one.
            $source->leads()->delete();
            $attributes['cursor'] = null;
        }
        $this->client?->leadSource()->updateOrCreate([], $attributes);
    }

    public function addUrl(): void
    {
        $this->authorizeOperator();
        abort_if($this->client === null, 404);
        $this->validate([
            'newUrl' => ['required', 'url:http,https', 'max:2048'],
            'newUrlLabel' => ['nullable', 'string', 'max:255'],
        ]);
        $this->client->monitoredUrls()->create(['url' => $this->newUrl, 'label' => $this->newUrlLabel ?: null]);
        $this->reset('newUrl', 'newUrlLabel');
    }

    public function toggleUrl(int $id): void
    {
        $this->authorizeOperator();
        $url = $this->monitoredUrl($id);
        $url->update(['is_active' => ! $url->is_active]);
    }

    public function removeUrl(int $id): void
    {
        $this->authorizeOperator();
        $this->monitoredUrl($id)->delete();
    }

    public function testLeadSource(LeadSync $sync): void
    {
        $this->authorizeOperator();
        $source = $this->client?->leadSource;
        if ($source === null) {
            $this->leadTestOk = false;
            $this->leadTestResult = __('Save the lead source first.');

            return;
        }
        $result = $sync->test($source);
        $this->leadTestOk = $result['ok'];
        $this->leadTestResult = $result['message'];
    }

    public function fetchLeadSource(): void
    {
        $this->authorizeOperator();
        if ($source = $this->client?->leadSource) {
            SyncLeadSourceNow::dispatch($source->id);
            session()->flash('status', __('Lead fetch queued.'));
        }
    }

    public function runNow(RunManager $runs): void
    {
        $user = $this->authorizeOperator();
        abort_if($this->client === null, 404);
        $run = $runs->start(RunTrigger::Manual, $this->client, $user);
        session()->flash('status', __('Run #:id started for this client.', ['id' => $run->id]));
    }

    public function delete(): void
    {
        $this->authorizeOperator();
        abort_if($this->client === null, 404);
        $name = $this->client->name;
        $this->client->delete();
        session()->flash('status', __('Client :name deleted with all its data.', ['name' => $name]));
        $this->redirectRoute('clients.index', navigate: true);
    }

    private function monitoredUrl(int $id): MonitoredUrl
    {
        return MonitoredUrl::query()->where('client_id', $this->client?->id)->findOrFail($id);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'client';
        $slug = $base;
        for ($i = 2; Client::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    public function render(): View
    {
        $this->client?->load(['connections', 'leadSource', 'monitoredUrls']);

        return view('livewire.clients.edit', [
            'timezones' => \DateTimeZone::listIdentifiers(),
            'currencies' => self::CURRENCIES,
            'platforms' => Platform::cases(),
            'leadTypes' => LeadSourceRegistry::availableTypes(),
            'leadScenarios' => MockScenarios::leadOptions(),
            'checks' => app(CheckRegistry::class)->all(),
            'inherited' => $this->inheritedCheckSettings($this->client),
            'readonly' => ! $this->canOperate(),
        ])->title($this->client->name ?? __('New client'));
    }
}
