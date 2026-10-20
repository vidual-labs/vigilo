{{-- Shared by /checks and the client page. Expects $checks (key => Check), $inherited, $readonly. --}}
<div class="divide-y divide-slate-100 dark:divide-slate-800">
    @foreach ($checks as $key => $check)
        <div class="py-4 first:pt-0 last:pb-0" wire:key="check-{{ $key }}">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="max-w-2xl">
                    <h3 class="text-sm font-semibold">{{ __('checks.'.$key.'.name') }}</h3>
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ __('checks.'.$key.'.description') }}</p>
                </div>
                <select wire:model="checkForm.{{ $key }}.enabled" class="v-input w-auto" @disabled($readonly) aria-label="{{ __('Enabled') }}">
                    <option value="">{{ __('Inherit') }} ({{ $inherited[$key]['enabled'] ? __('on') : __('off') }})</option>
                    <option value="1">{{ __('On') }}</option>
                    <option value="0">{{ __('Off') }}</option>
                </select>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4">
                @foreach ($check->fields() as $field => $type)
                    @php
                        $default = $inherited[$key]['values'][$field] ?? '';
                        $default = is_array($default) ? implode(', ', $default) : $default;
                    @endphp
                    <x-field :label="__('checks.'.$key.'.fields.'.$field)" :error="'checkForm.'.$key.'.values.'.$field">
                        @if ($type === 'severity')
                            <select wire:model="checkForm.{{ $key }}.values.{{ $field }}" class="v-input" @disabled($readonly)>
                                <option value="">{{ __('Inherit') }} ({{ \App\Domain\Findings\Enums\Severity::tryFrom((string) $default)?->label() }})</option>
                                @foreach (\App\Domain\Findings\Enums\Severity::cases() as $s)
                                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="text" inputmode="decimal" wire:model="checkForm.{{ $key }}.values.{{ $field }}" placeholder="{{ $default }}" class="v-input" @disabled($readonly)>
                        @endif
                    </x-field>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
