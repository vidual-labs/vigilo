<div>
    <x-page-header :title="__('Findings')" :subtitle="__('Everything vigilo found broken or wasting money, across all clients.')">
        <x-slot:actions>
            @if ($lastRun)
                <span class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Last run') }}: <x-time :at="$lastRun->started_at" /> · {{ $lastRun->status->label() }}
                </span>
            @endif
            @if ($this->canOperate())
                <button type="button" wire:click="runNow" wire:loading.attr="disabled" class="v-btn-primary">
                    {{ __('Run all checks now') }}
                </button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 grid grid-cols-3 gap-3">
        @foreach (['critical', 'warning', 'info'] as $sev)
            @php $enum = \App\Domain\Findings\Enums\Severity::from($sev); @endphp
            <button type="button" wire:click="$set('severity', '{{ $severity === $sev ? '' : $sev }}')"
                    @class(['v-card flex items-center justify-between px-4 py-3 text-left transition hover:border-slate-300 dark:hover:border-slate-600', 'ring-2 ring-teal-500' => $severity === $sev])>
                <span class="text-sm text-slate-600 dark:text-slate-300">{{ __('Open') }} · {{ $enum->label() }}</span>
                <span class="text-2xl font-semibold tabular-nums">{{ $counts[$sev] ?? 0 }}</span>
            </button>
        @endforeach
    </div>

    <div class="v-card mb-4 p-3">
        <div class="grid grid-cols-2 gap-2 md:grid-cols-6">
            <input type="search" wire:model.live.debounce.400ms="q" placeholder="{{ __('Search…') }}" class="v-input col-span-2 md:col-span-1">
            <select wire:model.live="client" class="v-input" aria-label="{{ __('Client') }}">
                <option value="">{{ __('All clients') }}</option>
                @foreach ($clients as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="severity" class="v-input" aria-label="{{ __('Severity') }}">
                <option value="">{{ __('All severities') }}</option>
                @foreach ($severities as $s)
                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                @endforeach
            </select>
            <select wire:model.live="status" class="v-input" aria-label="{{ __('Status') }}">
                <option value="active">{{ __('Open + acknowledged') }}</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                @endforeach
                <option value="all">{{ __('All statuses') }}</option>
            </select>
            <select wire:model.live="check" class="v-input" aria-label="{{ __('Check') }}">
                <option value="">{{ __('All checks') }}</option>
                @foreach ($checks as $key)
                    <option value="{{ $key }}">{{ __('checks.'.$key.'.name') }}</option>
                @endforeach
            </select>
            <select wire:model.live="platform" class="v-input" aria-label="{{ __('Platform') }}">
                <option value="">{{ __('All platforms') }}</option>
                @foreach ($platforms as $p)
                    <option value="{{ $p->value }}">{{ $p->label() }}</option>
                @endforeach
            </select>
        </div>
        @if ($client || $severity || $check || $platform || $q || $status !== 'active')
            <button type="button" wire:click="resetFilters" class="mt-2 text-xs text-teal-700 hover:underline dark:text-teal-400">{{ __('Reset filters') }}</button>
        @endif
    </div>

    @if ($this->canOperate() && count($selected) > 0)
        <div class="mb-3 flex flex-wrap items-center gap-2 rounded-md bg-slate-900 px-3 py-2 text-sm text-white dark:bg-slate-100 dark:text-slate-900">
            <span class="mr-2">{{ __(':count selected', ['count' => count($selected)]) }}</span>
            <button type="button" wire:click="bulk('acknowledge')" class="rounded bg-white/15 px-2 py-1 hover:bg-white/25 dark:bg-slate-900/10">{{ __('Acknowledge') }}</button>
            <button type="button" wire:click="bulk('snooze')" class="rounded bg-white/15 px-2 py-1 hover:bg-white/25 dark:bg-slate-900/10">{{ __('Snooze 7 days') }}</button>
            <button type="button" wire:click="bulk('resolve')" class="rounded bg-white/15 px-2 py-1 hover:bg-white/25 dark:bg-slate-900/10">{{ __('Resolve') }}</button>
        </div>
    @endif

    <div class="v-card overflow-hidden">
        @if ($findings->isEmpty())
            <x-empty :title="__('Nothing to see here.')" :text="__('No findings match these filters. When a check spots a problem it shows up here — and in tomorrow\'s digest.')" />
        @else
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($findings as $finding)
                    <li wire:key="finding-{{ $finding->id }}" class="flex gap-3 px-4 py-3 hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
                        @if ($this->canOperate())
                            <input type="checkbox" wire:model.live="selected" value="{{ $finding->id }}" class="mt-1 rounded border-slate-300 dark:border-slate-700" aria-label="{{ __('Select') }}">
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <x-severity :severity="$finding->severity" />
                                @if ($finding->status !== \App\Domain\Findings\Enums\FindingStatus::Open)
                                    <x-status-badge :status="$finding->status" />
                                @endif
                                @if ($finding->opened_at->greaterThan(now()->subDay()) && $finding->status->isActive())
                                    <span class="v-badge bg-teal-100 text-teal-800 dark:bg-teal-900/50 dark:text-teal-200">{{ __('New') }}</span>
                                @endif
                                <span class="text-xs text-slate-500 dark:text-slate-400">{{ $finding->client?->name }} · {{ $finding->checkLabel() }}</span>
                            </div>
                            <a href="{{ route('findings.show', $finding) }}" wire:navigate class="mt-1 block font-medium hover:underline">
                                {{ $finding->title() }}
                            </a>
                            <div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                {{ __('Detected') }} <x-time :at="$finding->opened_at" />
                                · {{ __('last seen') }} <x-time :at="$finding->last_detected_at" />
                                @if ($finding->status === \App\Domain\Findings\Enums\FindingStatus::Snoozed && $finding->snoozed_until)
                                    · {{ __('snoozed until :date', ['date' => $finding->snoozed_until->setTimezone(config('vigilo.timezone'))->isoFormat('L')]) }}
                                @endif
                            </div>
                        </div>
                        @if ($this->canOperate())
                            <div class="flex shrink-0 items-start gap-1" x-data="{ open: false }">
                                @if ($finding->status === \App\Domain\Findings\Enums\FindingStatus::Open)
                                    <button type="button" wire:click="acknowledge({{ $finding->id }})" class="v-btn-secondary px-2 py-1 text-xs">{{ __('Acknowledge') }}</button>
                                @elseif ($finding->status->isActive())
                                    <button type="button" wire:click="reopen({{ $finding->id }})" class="v-btn-secondary px-2 py-1 text-xs">{{ __('Reopen') }}</button>
                                @endif
                                @if ($finding->status->isActive())
                                    <div class="relative">
                                        <button type="button" @click="open = !open" @click.outside="open = false" class="v-btn-ghost text-xs" aria-label="{{ __('More') }}">⋯</button>
                                        <div x-show="open" x-cloak class="absolute right-0 z-20 mt-1 w-44 rounded-md border border-slate-200 bg-white py-1 text-sm shadow-lg dark:border-slate-700 dark:bg-slate-900">
                                            @foreach ($snoozeDays as $days)
                                                <button type="button" wire:click="snooze({{ $finding->id }}, {{ $days }})" @click="open = false" class="block w-full px-3 py-1.5 text-left hover:bg-slate-50 dark:hover:bg-slate-800">{{ trans_choice('Snooze :count day|Snooze :count days', $days, ['count' => $days]) }}</button>
                                            @endforeach
                                            <button type="button" wire:click="resolve({{ $finding->id }})" @click="open = false" class="block w-full px-3 py-1.5 text-left hover:bg-slate-50 dark:hover:bg-slate-800">{{ __('Mark resolved') }}</button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">
                {{ $findings->links('components.pagination') }}
            </div>
        @endif
    </div>
</div>
