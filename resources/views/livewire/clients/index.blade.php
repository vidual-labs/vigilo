<div>
    <x-page-header :title="__('Clients')" :subtitle="__('The agency clients vigilo watches. Each has its ad connections, budgets, lead source and landing pages.')">
        <x-slot:actions>
            @if ($this->canOperate())
                <a href="{{ route('clients.create') }}" wire:navigate class="v-btn-primary">{{ __('New client') }}</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="mb-3 flex flex-wrap items-center gap-3">
        <input type="search" wire:model.live.debounce.300ms="q" placeholder="{{ __('Search clients…') }}" class="v-input max-w-xs">
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model.live="inactive" class="rounded border-slate-300 dark:border-slate-700">
            {{ __('Show inactive') }}
        </label>
    </div>

    <div class="v-card overflow-x-auto">
        @if ($clients->isEmpty())
            <x-empty :title="__('No clients yet.')" :text="__('Add a client, connect its Google Ads and Meta accounts, and vigilo starts watching on the next run.')" />
        @else
            <table class="v-table">
                <thead class="bg-slate-50 dark:bg-slate-800/50">
                    <tr>
                        <th>{{ __('Client') }}</th>
                        <th>{{ __('Connections') }}</th>
                        <th>{{ __('Lead source') }}</th>
                        <th>{{ __('Monthly budget') }}</th>
                        <th>{{ __('Open findings') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($clients as $client)
                        @php($counts = $open[$client->id] ?? collect())
                        <tr wire:key="client-{{ $client->id }}">
                            <td>
                                <a href="{{ route('clients.edit', $client) }}" wire:navigate class="font-medium hover:underline">{{ $client->name }}</a>
                                <div class="text-xs text-slate-500">
                                    {{ $client->timezone }} · {{ $client->currency }}
                                    @if (! $client->is_active) · {{ __('inactive') }} @endif
                                    @if ($client->is_demo) · {{ __('demo') }} @endif
                                </div>
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($client->connections as $connection)
                                        <span class="v-badge {{ $connection->status->badgeClasses() }}" title="{{ $connection->displayName() }} · {{ $connection->status->label() }}">
                                            {{ $connection->platform->label() }}
                                        </span>
                                    @empty
                                        <span class="text-xs text-slate-400">{{ __('none') }}</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="text-sm">
                                @if ($client->leadSource)
                                    <span class="v-badge {{ $client->leadSource->status->badgeClasses() }}">{{ $client->leadSource->type->label() }}</span>
                                @else
                                    <span class="text-xs text-slate-400">{{ __('none') }}</span>
                                @endif
                            </td>
                            <td class="text-sm tabular-nums">
                                @forelse ($client->budgets as $budget)
                                    <div>{{ $budget->platform->label() }}: {{ \App\Support\Format::money((float) $budget->monthly_amount, $client->currency) }}</div>
                                @empty
                                    <span class="text-xs text-slate-400">—</span>
                                @endforelse
                            </td>
                            <td>
                                <a href="{{ route('findings.index', ['client' => $client->id]) }}" wire:navigate class="flex flex-wrap gap-1">
                                    @foreach (['critical', 'warning', 'info'] as $sev)
                                        @if (($counts[$sev] ?? 0) > 0)
                                            <span class="v-badge {{ \App\Domain\Findings\Enums\Severity::from($sev)->badgeClasses() }}">{{ $counts[$sev] }} {{ \App\Domain\Findings\Enums\Severity::from($sev)->label() }}</span>
                                        @endif
                                    @endforeach
                                    @if ($counts->sum() === 0)
                                        <span class="v-badge bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200">{{ __('All good') }}</span>
                                    @endif
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
