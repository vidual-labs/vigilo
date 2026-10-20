<div>
    <x-page-header :title="__('Connections')" :subtitle="__('Google Ads and Meta ad accounts, read-only. Credentials come from the server environment; a connection can override the token.')">
        <x-slot:actions>
            @if ($this->canOperate() && $editing === null)
                <button type="button" wire:click="create" class="v-btn-primary">{{ __('Add connection') }}</button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="mb-3">
        <select wire:model.live="client" class="v-input max-w-xs" aria-label="{{ __('Client') }}">
            <option value="">{{ __('All clients') }}</option>
            @foreach ($clients as $c)
                <option value="{{ $c->id }}">{{ $c->name }}</option>
            @endforeach
        </select>
    </div>

    @if ($editing !== null)
        <x-card :title="$editing ? __('Edit connection') : __('Add connection')" class="mb-4">
            <form wire:submit="save" class="grid gap-4 md:grid-cols-3">
                <x-field :label="__('Client')" error="form.client_id">
                    <select wire:model="form.client_id" class="v-input" required>
                        <option value="">—</option>
                        @foreach ($clients as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field :label="__('Platform')" error="form.platform">
                    <select wire:model.live="form.platform" class="v-input" @disabled($editing)>
                        @foreach ($platforms as $p)
                            <option value="{{ $p->value }}">{{ $p->label() }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field :label="__('Display name (optional)')" error="form.name">
                    <input type="text" wire:model="form.name" class="v-input">
                </x-field>
                @php($isGoogle = ($form['platform'] ?? 'google') === 'google')
                <x-field :label="$isGoogle ? __('Customer ID') : __('Ad account ID')" error="form.account_id" :hint="$isGoogle ? __('10 digits, as shown in Google Ads (123-456-7890).') : __('With or without the act_ prefix.')">
                    <input type="text" wire:model="form.account_id" class="v-input" placeholder="{{ $isGoogle ? '123-456-7890' : 'act_1234567890' }}" required>
                </x-field>
                @if ($isGoogle)
                    <x-field :label="__('Manager account ID (login-customer-id)')" error="form.login_customer_id" :hint="__('Only when access goes through a manager (MCC) account. Defaults to VIGILO_GOOGLE_ADS_LOGIN_CUSTOMER_ID.')">
                        <input type="text" wire:model="form.login_customer_id" class="v-input" placeholder="555-000-1234">
                    </x-field>
                @endif
                <x-field :label="$isGoogle ? __('Refresh token override (optional)') : __('Access token override (optional)')" error="form.token" :hint="__('Leave empty to use the agency-wide token from the environment. Stored encrypted; never shown again.')">
                    <input type="password" wire:model="form.token" class="v-input" autocomplete="off">
                    @if ($editing)
                        <label class="mt-1 flex items-center gap-2 text-xs"><input type="checkbox" wire:model="form.clear_token" class="rounded border-slate-300"> {{ __('Remove the stored override') }}</label>
                    @endif
                </x-field>
                @if (config('vigilo.mock_mode'))
                    <x-field :label="__('Simulated behaviour (mock mode)')" error="form.mock_scenario">
                        <select wire:model="form.mock_scenario" class="v-input">
                            @foreach ($scenarios as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-field>
                @endif
                <label class="flex items-center gap-2 text-sm md:col-span-3">
                    <input type="checkbox" wire:model="form.is_active" class="rounded border-slate-300 dark:border-slate-700">
                    {{ __('Active') }}
                </label>
                <div class="flex gap-2 md:col-span-3">
                    <button class="v-btn-primary">{{ __('Save') }}</button>
                    <button type="button" wire:click="cancel" class="v-btn-secondary">{{ __('Cancel') }}</button>
                </div>
            </form>
        </x-card>
    @endif

    <div class="v-card overflow-x-auto">
        @if ($connections->isEmpty())
            <x-empty :title="__('No connections yet.')" :text="__('Connect a Google Ads customer or a Meta ad account to a client. The README walks through getting the credentials.')" />
        @else
            <table class="v-table">
                <thead class="bg-slate-50 dark:bg-slate-800/50">
                    <tr>
                        <th>{{ __('Client') }}</th>
                        <th>{{ __('Account') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Last success') }}</th>
                        <th>{{ __('Token expires') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($connections as $connection)
                        <tr wire:key="conn-{{ $connection->id }}" @class(['opacity-60' => ! $connection->is_active])>
                            <td class="whitespace-nowrap"><a href="{{ route('clients.edit', $connection->client) }}" wire:navigate class="hover:underline">{{ $connection->client->name }}</a></td>
                            <td>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <x-platform-badge :platform="$connection->platform" />
                                    <span class="font-medium">{{ $connection->displayName() }}</span>
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ $connection->displayAccountId() }}
                                    @if ($connection->login_customer_id) · MCC {{ $connection->login_customer_id }} @endif
                                    @if ($connection->hasCredentialOverride()) · {{ __('own token') }} @endif
                                    @if (config('vigilo.mock_mode')) · {{ __('mock.'.$connection->mockScenario()) }} @endif
                                </div>
                                @if (isset($testResults[$connection->id]))
                                    <div @class(['mt-1 text-xs', 'text-emerald-700 dark:text-emerald-300' => $testResults[$connection->id]['ok'], 'text-rose-600 dark:text-rose-400' => ! $testResults[$connection->id]['ok']])>
                                        {{ $testResults[$connection->id]['ok'] ? __('Connection works: :name', ['name' => $testResults[$connection->id]['message']]) : $testResults[$connection->id]['message'] }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                <x-connection-status :status="$connection->status" />
                                @if ($connection->last_error)
                                    <div class="mt-1 max-w-xs text-xs text-rose-600 dark:text-rose-400" title="{{ $connection->last_error }}">{{ \Illuminate\Support\Str::limit($connection->last_error, 90) }}</div>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-sm"><x-time :at="$connection->last_success_at" /></td>
                            <td class="whitespace-nowrap text-sm">
                                @if ($connection->token_expires_at)
                                    <x-time :at="$connection->token_expires_at" />
                                @else
                                    <span class="text-slate-400">{{ $connection->platform === \App\Enums\Platform::Meta && $connection->token_checked_at ? __('never') : '—' }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <button type="button" wire:click="showLogs({{ $connection->id }})" class="v-btn-ghost text-xs">{{ __('Log') }}</button>
                                @if ($this->canOperate())
                                    <button type="button" wire:click="test({{ $connection->id }})" wire:loading.attr="disabled" class="v-btn-ghost text-xs">{{ __('Test') }}</button>
                                    <button type="button" wire:click="fetchNow({{ $connection->id }})" class="v-btn-ghost text-xs">{{ __('Fetch now') }}</button>
                                    <button type="button" wire:click="edit({{ $connection->id }})" class="v-btn-ghost text-xs">{{ __('Edit') }}</button>
                                    <button type="button" wire:click="toggle({{ $connection->id }})" class="v-btn-ghost text-xs">{{ $connection->is_active ? __('Pause') : __('Resume') }}</button>
                                    <button type="button" wire:click="delete({{ $connection->id }})" wire:confirm="{{ __('Delete this connection and its stored metrics?') }}" class="v-btn-ghost text-xs text-rose-600">{{ __('Delete') }}</button>
                                @endif
                            </td>
                        </tr>
                        @if ($logsFor === $connection->id)
                            <tr wire:key="logs-{{ $connection->id }}">
                                <td colspan="6" class="bg-slate-50 dark:bg-slate-800/40">
                                    <div class="mb-2 text-xs font-semibold uppercase text-slate-500">{{ __('Fetch log') }}</div>
                                    @forelse ($logs as $log)
                                        <div class="flex flex-wrap gap-x-3 py-0.5 font-mono text-xs">
                                            <span class="text-slate-500">{{ $log->started_at->setTimezone(config('vigilo.timezone'))->format('Y-m-d H:i:s') }}</span>
                                            <span class="w-16">{{ $log->kind }}</span>
                                            <span @class(['w-14', 'text-emerald-700 dark:text-emerald-400' => $log->status === 'success', 'text-rose-600 dark:text-rose-400' => $log->status !== 'success'])>{{ $log->status }}</span>
                                            <span class="w-20">HTTP {{ $log->http_status ?? '—' }}</span>
                                            <span class="w-20">{{ $log->attempts }}× · {{ $log->duration_ms }} ms</span>
                                            <span class="w-20">{{ $log->rows !== null ? $log->rows.' '.__('rows') : '' }}</span>
                                            <span class="text-rose-600 dark:text-rose-400">{{ $log->error }}</span>
                                        </div>
                                    @empty
                                        <div class="text-xs text-slate-500">{{ __('Not fetched yet.') }}</div>
                                    @endforelse
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
