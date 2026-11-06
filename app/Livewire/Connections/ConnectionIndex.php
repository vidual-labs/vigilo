<?php

namespace App\Livewire\Connections;

use App\Domain\AdPlatforms\Enums\ConnectionStatus;
use App\Domain\AdPlatforms\Mock\MockScenarios;
use App\Domain\AdPlatforms\Services\AdDataSync;
use App\Enums\Platform;
use App\Jobs\RunClientChecks;
use App\Jobs\SyncConnectionNow;
use App\Livewire\Concerns\ChecksOperator;
use App\Models\AdConnection;
use App\Models\Client;
use App\Models\FetchLog;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

class ConnectionIndex extends Component
{
    use ChecksOperator;

    /** What a fetch learns about the account (AccountInfo::toSettings()). */
    private const ACCOUNT_SETTINGS = ['account_name', 'account_currency', 'account_timezone', 'account_active', 'account_status'];

    #[Url(except: '')]
    public string $client = '';

    /** null = form closed, 0 = new connection, id = editing. */
    public ?int $editing = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public ?int $logsFor = null;

    /** @var array<int, array{ok: bool, message: string}> */
    public array $testResults = [];

    public function create(): void
    {
        $this->authorizeOperator();
        $this->resetValidation();
        $this->editing = 0;
        $this->form = [
            'client_id' => $this->client,
            'platform' => Platform::Google->value,
            'name' => '',
            'account_id' => '',
            'login_customer_id' => '',
            'token' => '',
            'clear_token' => false,
            'mock_scenario' => 'healthy',
            'meta_conversion_action_types' => '',
            'is_active' => true,
        ];
    }

    public function edit(int $id): void
    {
        $this->authorizeOperator();
        $this->resetValidation();
        $connection = $this->find($id);
        $this->editing = $connection->id;
        $this->form = [
            'client_id' => (string) $connection->client_id,
            'platform' => $connection->platform->value,
            'name' => (string) $connection->name,
            'account_id' => $connection->displayAccountId(),
            'login_customer_id' => (string) $connection->login_customer_id,
            'token' => '',
            'clear_token' => false,
            'mock_scenario' => $connection->mockScenario(),
            'meta_conversion_action_types' => implode(', ', (array) $connection->setting('meta_conversion_action_types', [])),
            'is_active' => $connection->is_active,
        ];
    }

    public function cancel(): void
    {
        $this->editing = null;
        $this->form = [];
    }

    public function save(): void
    {
        $this->authorizeOperator();
        $this->validate([
            'form.client_id' => ['required', Rule::exists('clients', 'id')],
            'form.platform' => ['required', Rule::enum(Platform::class)],
            'form.name' => ['nullable', 'string', 'max:255'],
            'form.account_id' => ['required', 'string', 'max:64', 'regex:/^(act_)?[\d\- ]+$/'],
            'form.login_customer_id' => ['nullable', 'string', 'max:32', 'regex:/^[\d\- ]+$/'],
            'form.token' => ['nullable', 'string', 'max:4096'],
            'form.mock_scenario' => ['nullable', Rule::in(MockScenarios::AD_SCENARIOS)],
            'form.meta_conversion_action_types' => ['nullable', 'string', 'max:500', 'regex:/^[A-Za-z0-9_.,\s]*$/'],
        ], attributes: ['form.account_id' => __('account id'), 'form.meta_conversion_action_types' => __('conversion action types')]);

        $platform = Platform::from($this->form['platform']);
        $accountId = AdConnection::normalizeAccountId($platform, (string) $this->form['account_id']);
        $connection = $this->editing ? $this->find($this->editing) : new AdConnection;

        $duplicate = AdConnection::query()
            ->where('client_id', $this->form['client_id'])->where('platform', $platform->value)->where('account_id', $accountId)
            ->when($connection->exists, fn ($q) => $q->whereKeyNot($connection->id))->exists();
        if ($duplicate) {
            $this->addError('form.account_id', __('This account is already connected to the client.'));

            return;
        }

        if ($connection->exists && ($connection->account_id !== $accountId || $connection->client_id !== (int) $this->form['client_id'])) {
            // Another account or client: none of the stored numbers, ads or
            // health apply any more. A last success from the old account
            // would make the empty new one look fresh (e.g. "spending
            // behind plan" at 0 %) until the next fetch.
            $connection->metrics()->delete();
            $connection->ads()->delete();
            $connection->forceFill([
                'status' => ConnectionStatus::Unknown,
                'last_attempt_at' => null,
                'last_success_at' => null,
                'last_error' => null,
                'last_error_code' => null,
                'consecutive_failures' => 0,
                'token_expires_at' => null,
                'token_checked_at' => null,
                'settings' => array_diff_key($connection->settings ?? [], array_flip(self::ACCOUNT_SETTINGS)),
            ]);
        }

        $connection->fill([
            'client_id' => (int) $this->form['client_id'],
            'platform' => $platform,
            'name' => $this->form['name'] ?: null,
            'account_id' => $accountId,
            'login_customer_id' => $platform === Platform::Google ? (preg_replace('/\D/', '', (string) $this->form['login_customer_id']) ?: null) : null,
            'is_active' => (bool) $this->form['is_active'],
            'settings' => array_merge($connection->settings ?? [], [
                'mock_scenario' => $this->form['mock_scenario'] ?: 'healthy',
                // Empty = use the install default (VIGILO_META_CONVERSION_ACTION_TYPES).
                'meta_conversion_action_types' => $platform === Platform::Meta
                    ? AdConnection::parseActionTypes((string) ($this->form['meta_conversion_action_types'] ?? ''))
                    : [],
            ]),
        ]);

        // Token override: Google → refresh token, Meta → access token. Blank keeps the old one.
        $key = $platform === Platform::Google ? 'refresh_token' : 'access_token';
        if ($this->form['clear_token'] ?? false) {
            $connection->credentials = null;
        } elseif (trim((string) $this->form['token']) !== '') {
            $connection->credentials = [$key => trim((string) $this->form['token'])];
        }
        $connection->save();

        $this->cancel();
        session()->flash('status', __('Connection saved. Use “Test” to check the credentials.'));
    }

    public function test(int $id, AdDataSync $sync): void
    {
        $this->authorizeOperator();
        $this->testResults[$id] = $sync->test($this->find($id));
    }

    public function fetchNow(int $id): void
    {
        $this->authorizeOperator();
        $connection = $this->find($id);
        Bus::chain([new SyncConnectionNow($connection->id), new RunClientChecks($connection->client_id)])->dispatch();
        session()->flash('status', __('Fetch queued for :name. Checks for the client run right after.', ['name' => $connection->displayName()]));
    }

    public function toggle(int $id): void
    {
        $this->authorizeOperator();
        $connection = $this->find($id);
        $connection->update(['is_active' => ! $connection->is_active]);
    }

    public function delete(int $id): void
    {
        $this->authorizeOperator();
        $this->find($id)->delete();
        session()->flash('status', __('Connection deleted with its stored metrics.'));
    }

    public function showLogs(int $id): void
    {
        $this->logsFor = $this->logsFor === $id ? null : $id;
    }

    private function find(int $id): AdConnection
    {
        return AdConnection::query()->whereHas('client', fn ($q) => $q->visibleTo(auth()->user()))->findOrFail($id);
    }

    public function render(): View
    {
        $user = auth()->user();
        $connections = AdConnection::query()
            ->whereHas('client', fn ($q) => $q->visibleTo($user))
            ->with('client')
            ->when($this->client !== '', fn ($q) => $q->where('client_id', (int) $this->client))
            ->join('clients', 'clients.id', '=', 'ad_connections.client_id')
            ->orderBy('clients.name')->orderBy('ad_connections.platform')
            ->select('ad_connections.*')
            ->get();

        return view('livewire.connections.index', [
            'connections' => $connections,
            'clients' => Client::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']),
            'platforms' => Platform::cases(),
            'scenarios' => MockScenarios::adOptions(isset($this->form['platform']) ? Platform::tryFrom($this->form['platform']) : null),
            'logs' => $this->logsFor
                ? FetchLog::query()->where('subject_type', (new AdConnection)->getMorphClass())->where('subject_id', $this->logsFor)->latest('started_at')->limit(20)->get()
                : collect(),
        ])->title(__('Connections'));
    }
}
