<div>
    <x-page-header :title="__('Notifications')" :subtitle="__('A daily digest by email (critical first), immediate mails for new critical findings, and outgoing webhooks for n8n, Slack and friends.')" />

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('Webhooks')" :description="__('vigilo POSTs JSON to each endpoint, signed with HMAC-SHA256. Events: digest (after the daily run) and finding.critical (as it happens).')">
                @if ($revealedSecret)
                    <div class="mb-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm dark:border-amber-700 dark:bg-amber-950">
                        <div class="font-medium">{{ __('Signing secret — copy it now, it is not shown again:') }}</div>
                        <code class="mt-1 block break-all font-mono text-xs">{{ $revealedSecret }}</code>
                    </div>
                @endif

                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($endpoints as $endpoint)
                        <li class="py-3" wire:key="hook-{{ $endpoint->id }}">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="font-medium">{{ $endpoint->name }} @unless ($endpoint->is_active)<span class="text-xs text-slate-500">· {{ __('paused') }}</span>@endunless</div>
                                    <div class="break-all text-xs text-slate-500">{{ $endpoint->url }}</div>
                                    <div class="mt-1 text-xs text-slate-500">
                                        {{ __('Last delivery') }}: <x-time :at="$endpoint->last_delivered_at" />
                                        @if ($endpoint->last_status) · HTTP {{ $endpoint->last_status }} @endif
                                        @if ($endpoint->last_error) · <span class="text-rose-600">{{ \Illuminate\Support\Str::limit($endpoint->last_error, 100) }}</span> @endif
                                    </div>
                                    @if (isset($testResults[$endpoint->id]))
                                        <div class="mt-1 text-xs">{{ $testResults[$endpoint->id] }}</div>
                                    @endif
                                </div>
                                @if ($this->canOperate())
                                    <div class="flex flex-wrap items-center gap-1 text-xs">
                                        <label class="flex items-center gap-1"><input type="checkbox" @checked($endpoint->send_digest) wire:click="toggle({{ $endpoint->id }}, 'send_digest')" class="rounded border-slate-300"> {{ __('digest') }}</label>
                                        <label class="ml-2 flex items-center gap-1"><input type="checkbox" @checked($endpoint->send_critical) wire:click="toggle({{ $endpoint->id }}, 'send_critical')" class="rounded border-slate-300"> {{ __('critical') }}</label>
                                        <button type="button" wire:click="sendTest({{ $endpoint->id }})" class="v-btn-ghost text-xs">{{ __('Send test') }}</button>
                                        <button type="button" wire:click="toggle({{ $endpoint->id }}, 'is_active')" class="v-btn-ghost text-xs">{{ $endpoint->is_active ? __('Pause') : __('Resume') }}</button>
                                        <button type="button" wire:click="rotate({{ $endpoint->id }})" wire:confirm="{{ __('Create a new secret? The old one stops working immediately.') }}" class="v-btn-ghost text-xs">{{ __('New secret') }}</button>
                                        <button type="button" wire:click="delete({{ $endpoint->id }})" wire:confirm="{{ __('Delete this webhook?') }}" class="v-btn-ghost text-xs text-rose-600">{{ __('Delete') }}</button>
                                    </div>
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="py-2 text-sm text-slate-500">{{ __('No webhooks yet.') }}</li>
                    @endforelse
                </ul>

                @if ($this->canOperate())
                    <form wire:submit="addEndpoint" class="mt-4 grid gap-3 border-t border-slate-100 pt-4 md:grid-cols-2 dark:border-slate-800">
                        <x-field :label="__('Name')" error="name">
                            <input type="text" wire:model="name" class="v-input" placeholder="n8n">
                        </x-field>
                        <x-field :label="__('URL')" error="url">
                            <input type="url" wire:model="url" class="v-input" placeholder="https://n8n.example.com/webhook/vigilo">
                        </x-field>
                        <div class="flex flex-wrap items-center gap-4 text-sm md:col-span-2">
                            <label class="flex items-center gap-2"><input type="checkbox" wire:model="sendDigest" class="rounded border-slate-300"> {{ __('Daily digest') }}</label>
                            <label class="flex items-center gap-2"><input type="checkbox" wire:model="sendCritical" class="rounded border-slate-300"> {{ __('Critical findings') }}</label>
                            <button class="v-btn-primary ml-auto">{{ __('Add webhook') }}</button>
                        </div>
                    </form>
                @endif
            </x-card>

            <x-card :title="__('Verifying the signature')">
                <p class="mb-2 text-sm text-slate-600 dark:text-slate-300">{{ __('Each request carries X-Vigilo-Event, X-Vigilo-Timestamp, X-Vigilo-Delivery and X-Vigilo-Signature. Recompute the signature over "timestamp.body" and compare:') }}</p>
                <pre class="overflow-x-auto rounded-md bg-slate-900 p-3 font-mono text-xs text-slate-100">expected = "sha256=" + hex(hmac_sha256(secret, timestamp + "." + raw_body))
reject if expected != X-Vigilo-Signature or |now - timestamp| > 300</pre>
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card :title="__('Email')">
                <dl class="space-y-1.5 text-sm">
                    <div class="flex justify-between gap-2"><dt class="text-slate-500">{{ __('Mailer') }}</dt><dd class="font-mono">{{ $mailer }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-500">{{ __('From') }}</dt><dd class="break-all font-mono text-xs">{{ $from }}</dd></div>
                </dl>
                @if ($mailer === 'log')
                    <p class="mt-3 text-sm text-amber-700 dark:text-amber-300">{{ __('Mail goes to the log file until MAIL_MAILER and the SMTP settings are configured in .env.') }}</p>
                @endif
                <p class="mt-3 text-xs text-slate-500">{{ __('Who receives the digest and critical mails is set per user on the profile page.') }}</p>
                @if ($this->canOperate())
                    <button type="button" wire:click="sendDigestNow" class="v-btn-secondary mt-3 w-full">{{ __('Send digest now') }}</button>
                @endif
            </x-card>
        </div>
    </div>
</div>
