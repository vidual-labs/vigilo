@php($open = \App\Domain\Findings\Enums\FindingStatus::Open)
<div>
    <a href="{{ route('findings.index') }}" wire:navigate class="mb-3 inline-block text-sm text-slate-500 hover:underline">← {{ __('All findings') }}</a>

    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="mb-1 flex flex-wrap items-center gap-1.5">
                <x-severity :severity="$finding->severity" />
                <x-status-badge :status="$finding->status" />
                @if ($finding->platform)
                    <x-platform-badge :platform="$finding->platform" />
                @endif
            </div>
            <h1 class="text-xl font-semibold tracking-tight">{{ $finding->title() }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                <a href="{{ route('clients.edit', $finding->client) }}" wire:navigate class="hover:underline">{{ $finding->client->name }}</a>
                · {{ $finding->checkLabel() }}
            </p>
        </div>
        @if ($this->canOperate() && $finding->status->isActive())
            <div class="flex flex-wrap gap-2" x-data="{ open: false }">
                @if ($finding->status === $open)
                    <button type="button" wire:click="acknowledge" class="v-btn-secondary">{{ __('Acknowledge') }}</button>
                @else
                    <button type="button" wire:click="reopen" class="v-btn-secondary">{{ __('Reopen') }}</button>
                @endif
                <div class="relative">
                    <button type="button" class="v-btn-secondary" @click="open = !open" @click.outside="open = false">{{ __('Snooze') }} ▾</button>
                    <div x-show="open" x-cloak class="absolute right-0 z-20 mt-1 w-40 rounded-md border border-slate-200 bg-white py-1 text-sm shadow-lg dark:border-slate-700 dark:bg-slate-900">
                        @foreach ($snoozeDays as $days)
                            <button type="button" wire:click="snooze({{ $days }})" @click="open = false" class="block w-full px-3 py-1.5 text-left hover:bg-slate-50 dark:hover:bg-slate-800">{{ trans_choice(':count day|:count days', $days, ['count' => $days]) }}</button>
                        @endforeach
                    </div>
                </div>
                <button type="button" wire:click="resolve" class="v-btn-primary">{{ __('Mark resolved') }}</button>
            </div>
        @endif
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('What vigilo saw')">
                <p class="mb-4 text-sm text-slate-600 dark:text-slate-300">{{ __('checks.'.$finding->check_key.'.description') }}</p>
                <x-evidence :evidence="$finding->evidence" />
            </x-card>
            <x-card :title="__('What to do')">
                <p class="text-sm text-slate-600 dark:text-slate-300">{{ __('checks.'.$finding->check_key.'.advice') }}</p>
                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ __('vigilo is read-only: it never changes campaigns, budgets or keywords. Fix the cause in the ad platform; the finding resolves itself on the next run.') }}</p>
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card :title="__('Details')">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-2"><dt class="text-slate-500">{{ __('First detected') }}</dt><dd><x-time :at="$finding->first_detected_at" /></dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-500">{{ __('Open since') }}</dt><dd><x-time :at="$finding->opened_at" /></dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-500">{{ __('Last seen') }}</dt><dd><x-time :at="$finding->last_detected_at" /></dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-500">{{ __('Seen in runs') }}</dt><dd class="tabular-nums">{{ $finding->occurrences }}</dd></div>
                    @if ($finding->reopen_count)
                        <div class="flex justify-between gap-2"><dt class="text-slate-500">{{ __('Came back') }}</dt><dd class="tabular-nums">{{ trans_choice(':count time|:count times', $finding->reopen_count, ['count' => $finding->reopen_count]) }}</dd></div>
                    @endif
                    @if ($finding->acknowledgedBy)
                        <div class="flex justify-between gap-2"><dt class="text-slate-500">{{ __('Acknowledged by') }}</dt><dd>{{ $finding->acknowledgedBy->name }}</dd></div>
                    @endif
                    @if ($finding->snoozed_until)
                        <div class="flex justify-between gap-2"><dt class="text-slate-500">{{ __('Snoozed until') }}</dt><dd>{{ $finding->snoozed_until->setTimezone(config('vigilo.timezone'))->isoFormat('L LT') }}</dd></div>
                    @endif
                    @if ($finding->resolved_at)
                        <div class="flex justify-between gap-2"><dt class="text-slate-500">{{ __('Resolved') }}</dt><dd><x-time :at="$finding->resolved_at" /> · {{ $finding->resolvedBy?->name ?? __('automatically') }}</dd></div>
                    @endif
                </dl>
            </x-card>

            <x-card :title="__('History')">
                <ol class="space-y-2.5 text-sm">
                    @foreach ($finding->events as $event)
                        <li class="flex gap-2">
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-slate-300 dark:bg-slate-600"></span>
                            <div>
                                <div>
                                    {{ $event->type->label() }}
                                    @if (isset($event->data['to']))
                                        → {{ \App\Domain\Findings\Enums\Severity::tryFrom($event->data['to'])?->label() }}
                                    @endif
                                    @if ($event->user)
                                        <span class="text-slate-500">· {{ $event->user->name }}</span>
                                    @elseif ($event->type === \App\Domain\Findings\Enums\FindingEventType::Resolved)
                                        <span class="text-slate-500">· {{ __('automatically') }}</span>
                                    @endif
                                </div>
                                <x-time :at="$event->created_at" class="text-xs text-slate-500" />
                            </div>
                        </li>
                    @endforeach
                </ol>
            </x-card>
        </div>
    </div>
</div>
