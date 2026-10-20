<div>
    <x-page-header :title="__('Checks')" :subtitle="__('Install-wide defaults. Every client can override them on its settings page; empty fields use the built-in default shown in grey.')" />

    <div class="grid gap-4 lg:grid-cols-3">
        <form wire:submit="save" class="lg:col-span-2">
            <x-card :title="__('Watchdog checks')">
                @include('livewire.partials.check-form')
                @if ($this->canOperate())
                    <div class="mt-4 flex justify-end border-t border-slate-100 pt-4 dark:border-slate-800">
                        <button class="v-btn-primary">{{ __('Save check settings') }}</button>
                    </div>
                @endif
            </x-card>
        </form>

        <div class="space-y-4">
            <x-card :title="__('Runs')" :description="__('The daily run fetches every client, runs all checks and sends the digest. Scheduled at :time.', ['time' => $schedule])">
                <x-slot:actions>
                    @if ($this->canOperate())
                        <button type="button" wire:click="runNow" class="v-btn-secondary px-2 py-1 text-xs">{{ __('Run now') }}</button>
                    @endif
                </x-slot:actions>
                <ul class="space-y-2 text-sm">
                    @forelse ($runs as $run)
                        <li class="border-b border-slate-100 pb-2 last:border-0 dark:border-slate-800">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-medium">#{{ $run->id }} · {{ $run->trigger->label() }}{{ $run->client ? ' · '.$run->client->name : '' }}</span>
                                <span class="text-xs text-slate-500"><x-time :at="$run->started_at" /></span>
                            </div>
                            <div class="text-xs text-slate-500">
                                {{ $run->status->label() }}
                                @if ($run->finished_at) · {{ $run->started_at->diffInSeconds($run->finished_at) }} s @endif
                                @if ($run->stats)
                                    · {{ __(':opened new, :resolved resolved, :failures fetch errors', ['opened' => $run->stats['opened'] ?? 0, 'resolved' => $run->stats['resolved'] ?? 0, 'failures' => $run->stats['fetch_failures'] ?? 0]) }}
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="text-slate-500">{{ __('No runs yet.') }}</li>
                    @endforelse
                </ul>
            </x-card>

            <x-card :title="__('How findings behave')">
                <ul class="list-inside list-disc space-y-1 text-sm text-slate-600 dark:text-slate-300">
                    <li>{{ __('The same problem stays one finding; it is not re-alerted every day.') }}</li>
                    <li>{{ __('When the condition clears, the finding resolves itself.') }}</li>
                    <li>{{ __('If it gets worse (warning → critical) you are alerted again.') }}</li>
                    <li>{{ __('Acknowledge = known, keep visible. Snooze = hide until a date.') }}</li>
                    <li>{{ __('Missing data never resolves a finding: a failed fetch keeps it open.') }}</li>
                </ul>
            </x-card>
        </div>
    </div>
</div>
