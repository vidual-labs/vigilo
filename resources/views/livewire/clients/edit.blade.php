<div>
    <a href="{{ route('clients.index') }}" wire:navigate class="mb-3 inline-block text-sm text-slate-500 hover:underline">← {{ __('All clients') }}</a>

    <x-page-header :title="$client?->name ?? __('New client')" :subtitle="$client ? __('Settings, budgets, lead source, landing pages and check thresholds for this client.') : __('Create the client first; connections are added on the next screen.')">
        @if ($client && $this->canOperate())
            <x-slot:actions>
                <a href="{{ route('findings.index', ['client' => $client->id]) }}" wire:navigate class="v-btn-secondary">{{ __('Findings') }}</a>
                <button type="button" wire:click="runNow" class="v-btn-secondary">{{ __('Fetch & check now') }}</button>
            </x-slot:actions>
        @endif
    </x-page-header>

    @if (! $this->canOperate())
        <p class="mb-4 rounded-md bg-slate-100 px-3 py-2 text-sm text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ __('You have read-only access.') }}</p>
    @endif

    <form wire:submit="save" class="space-y-4">
        <x-card :title="__('General')">
            <div class="grid gap-4 md:grid-cols-2">
                <x-field :label="__('Name')" for="name" error="name">
                    <input id="name" type="text" wire:model="name" class="v-input" @disabled($readonly) required>
                </x-field>
                <div class="grid grid-cols-2 gap-3">
                    <x-field :label="__('Timezone')" for="timezone" error="timezone">
                        <select id="timezone" wire:model="timezone" class="v-input" @disabled($readonly)>
                            @foreach ($timezones as $tz)
                                <option value="{{ $tz }}">{{ $tz }}</option>
                            @endforeach
                        </select>
                    </x-field>
                    <x-field :label="__('Currency')" for="currency" error="currency">
                        <select id="currency" wire:model="currency" class="v-input" @disabled($readonly)>
                            @foreach ($currencies as $c)
                                <option value="{{ $c }}">{{ $c }}</option>
                            @endforeach
                        </select>
                    </x-field>
                </div>
                <x-field :label="__('Notes')" for="notes" error="notes" class="md:col-span-2" :hint="__('Internal notes for the agency. Do not put lead or personal data here.')">
                    <textarea id="notes" wire:model="notes" rows="2" class="v-input" @disabled($readonly)></textarea>
                </x-field>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="isActive" class="rounded border-slate-300 dark:border-slate-700" @disabled($readonly)>
                    {{ __('Active (inactive clients are not fetched or checked)') }}
                </label>
            </div>
        </x-card>

        <x-card :title="__('Monthly budgets')" :description="__('Used by the budget pacing check. Leave empty for platforms without a fixed budget.')">
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($platforms as $platform)
                    <x-field :label="$platform->label().' ('.$currency.')'" :error="'budgets.'.$platform->value">
                        <input type="text" inputmode="decimal" wire:model="budgets.{{ $platform->value }}" class="v-input" placeholder="—" @disabled($readonly)>
                    </x-field>
                @endforeach
            </div>
        </x-card>

        <x-card :title="__('Lead source')" :description="__('Where this client\'s leads arrive. vigilo reads lead metadata only (time, status, ad ids) — never names, emails, phone numbers or answers.')">
            <div class="grid gap-4 md:grid-cols-3">
                <x-field :label="__('Source')" error="leadType">
                    <select wire:model.live="leadType" class="v-input" @disabled($readonly)>
                        <option value="">{{ __('None') }}</option>
                        @foreach ($leadTypes as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </x-field>
                @if ($leadType === 'lodgely')
                    <x-field :label="__('lodgely client key')" error="leadKey" :hint="__('The client key from lodgely (see docs/LODGELY-CONTRACT.md).')">
                        <input type="text" wire:model="leadKey" class="v-input" placeholder="kanzlei-brenner" @disabled($readonly)>
                    </x-field>
                @endif
                @if ($leadType !== '' && config('vigilo.mock_mode'))
                    <x-field :label="__('Simulated behaviour')" error="leadScenario">
                        <select wire:model="leadScenario" class="v-input" @disabled($readonly)>
                            @foreach ($leadScenarios as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-field>
                @endif
            </div>
            @if ($leadType !== '')
                <label class="mt-3 flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="leadActive" class="rounded border-slate-300 dark:border-slate-700" @disabled($readonly)>
                    {{ __('Fetch leads from this source') }}
                </label>
            @endif
            @if ($leadType === 'lodgely' && ! config('vigilo.lodgely.enabled') && ! config('vigilo.mock_mode'))
                <p class="mt-3 text-sm text-amber-700 dark:text-amber-300">{{ __('The lodgely integration is switched off (VIGILO_LODGELY_ENABLED=false).') }}</p>
            @endif
            @if ($client?->leadSource)
                @php($source = $client->leadSource)
                <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-3 text-sm dark:border-slate-800">
                    <x-connection-status :status="$source->status" />
                    <span class="text-slate-500">{{ __('Last success') }}: <x-time :at="$source->last_success_at" /></span>
                    <span class="text-slate-500">{{ __('Leads stored') }}: {{ $source->leads()->count() }}</span>
                    @if ($source->last_error)
                        <span class="text-rose-600 dark:text-rose-400">{{ \Illuminate\Support\Str::limit($source->last_error, 140) }}</span>
                    @endif
                    @if ($this->canOperate())
                        <span class="ml-auto flex gap-2">
                            <button type="button" wire:click="testLeadSource" class="v-btn-secondary px-2 py-1 text-xs">{{ __('Test connection') }}</button>
                            <button type="button" wire:click="fetchLeadSource" class="v-btn-secondary px-2 py-1 text-xs">{{ __('Fetch now') }}</button>
                        </span>
                    @endif
                </div>
                @if ($leadTestResult)
                    <p @class(['mt-2 text-sm', 'text-emerald-700 dark:text-emerald-300' => $leadTestOk, 'text-rose-600 dark:text-rose-400' => ! $leadTestOk])>{{ $leadTestResult }}</p>
                @endif
            @endif
        </x-card>

        <x-card :title="__('Check thresholds for this client')" :description="__('Empty fields use the install-wide value from the Checks page (shown in grey).')">
            @include('livewire.partials.check-form')
        </x-card>

        @if ($this->canOperate())
            <div class="flex justify-end">
                <button class="v-btn-primary" wire:loading.attr="disabled">{{ $client ? __('Save changes') : __('Create client') }}</button>
            </div>
        @endif
    </form>

    @if ($client)
        <div class="mt-4 space-y-4">
            <x-card :title="__('Ad connections')">
                <x-slot:actions>
                    <a href="{{ route('connections.index', ['client' => $client->id]) }}" wire:navigate class="v-btn-secondary px-2 py-1 text-xs">{{ __('Manage connections') }}</a>
                </x-slot:actions>
                @forelse ($client->connections as $connection)
                    <div class="flex flex-wrap items-center gap-2 py-1.5 text-sm">
                        <x-platform-badge :platform="$connection->platform" />
                        <span class="font-medium">{{ $connection->displayName() }}</span>
                        <span class="text-slate-500">{{ $connection->displayAccountId() }}</span>
                        <x-connection-status :status="$connection->status" />
                        <span class="text-xs text-slate-500">{{ __('Last success') }}: <x-time :at="$connection->last_success_at" /></span>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">{{ __('No connections yet.') }}</p>
                @endforelse
            </x-card>

            <x-card :title="__('Monitored URLs')" :description="__('Pages checked every day in addition to the landing pages of active ads (forms, booking pages, calculators). vigilo only loads them — it never submits a form.')">
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($client->monitoredUrls as $url)
                        <li class="flex flex-wrap items-center gap-2 py-2 text-sm" wire:key="url-{{ $url->id }}">
                            <span @class(['break-all', 'text-slate-400 line-through' => ! $url->is_active])>{{ $url->url }}</span>
                            @if ($url->label)
                                <span class="text-slate-500">· {{ $url->label }}</span>
                            @endif
                            @if ($this->canOperate())
                                <span class="ml-auto flex gap-1">
                                    <button type="button" wire:click="toggleUrl({{ $url->id }})" class="v-btn-ghost text-xs">{{ $url->is_active ? __('Pause') : __('Resume') }}</button>
                                    <button type="button" wire:click="removeUrl({{ $url->id }})" wire:confirm="{{ __('Remove this URL?') }}" class="v-btn-ghost text-xs text-rose-600">{{ __('Remove') }}</button>
                                </span>
                            @endif
                        </li>
                    @empty
                        <li class="py-2 text-sm text-slate-500">{{ __('No monitored URLs.') }}</li>
                    @endforelse
                </ul>
                @if ($this->canOperate())
                    <form wire:submit="addUrl" class="mt-3 grid gap-2 md:grid-cols-[2fr_1fr_auto]">
                        <x-field :label="__('URL')" error="newUrl">
                            <input type="url" wire:model="newUrl" class="v-input" placeholder="https://www.example.com/kontakt">
                        </x-field>
                        <x-field :label="__('Label')" error="newUrlLabel">
                            <input type="text" wire:model="newUrlLabel" class="v-input" placeholder="{{ __('Contact form') }}">
                        </x-field>
                        <div class="flex items-end"><button class="v-btn-secondary w-full">{{ __('Add URL') }}</button></div>
                    </form>
                @endif
            </x-card>

            @if ($this->canOperate())
                <x-card :title="__('Danger zone')">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm text-slate-600 dark:text-slate-300">{{ __('Deleting a client removes its connections, metrics, lead metadata and findings for good.') }}</p>
                        <button type="button" wire:click="delete" wire:confirm="{{ __('Delete :name and all its data? This cannot be undone.', ['name' => $client->name]) }}" class="v-btn-danger">{{ __('Delete client') }}</button>
                    </div>
                </x-card>
            @endif
        </div>
    @endif
</div>
