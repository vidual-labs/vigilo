<div>
    <x-page-header :title="__('Users')" :subtitle="__('Operators see and change everything; viewers see everything and change nothing.')" />

    @if ($revealedPassword)
        <div class="mb-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm dark:border-amber-700 dark:bg-amber-950">
            <div class="font-medium">{{ __('Password for :email — copy it now, it is not shown again. They will be asked to change it after signing in.', ['email' => $revealedFor]) }}</div>
            <code class="mt-1 block break-all font-mono">{{ $revealedPassword }}</code>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="v-card overflow-x-auto lg:col-span-2">
            <table class="v-table">
                <thead class="bg-slate-50 dark:bg-slate-800/50">
                    <tr><th>{{ __('Name') }}</th><th>{{ __('Role') }}</th><th>{{ __('Last sign-in') }}</th><th></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($users as $user)
                        <tr wire:key="user-{{ $user->id }}" @class(['opacity-60' => ! $user->is_active])>
                            <td>
                                <div class="font-medium">{{ $user->name }}</div>
                                <div class="text-xs text-slate-500">{{ $user->email }} · {{ strtoupper($user->locale) }} @unless ($user->is_active) · {{ __('deactivated') }} @endunless</div>
                            </td>
                            <td>
                                @if ($user->is(auth()->user()))
                                    {{ $user->role->label() }}
                                @else
                                    <select wire:change="setRole({{ $user->id }}, $event.target.value)" class="v-input py-1 text-xs">
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->value }}" @selected($user->role === $role)>{{ $role->label() }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-sm"><x-time :at="$user->last_login_at" :empty="__('never')" /></td>
                            <td class="whitespace-nowrap text-right">
                                <button type="button" wire:click="resetPassword({{ $user->id }})" wire:confirm="{{ __('Generate a new password for :name?', ['name' => $user->name]) }}" class="v-btn-ghost text-xs">{{ __('New password') }}</button>
                                @unless ($user->is(auth()->user()))
                                    <button type="button" wire:click="toggleActive({{ $user->id }})" class="v-btn-ghost text-xs">{{ $user->is_active ? __('Deactivate') : __('Activate') }}</button>
                                    <button type="button" wire:click="delete({{ $user->id }})" wire:confirm="{{ __('Delete :name?', ['name' => $user->name]) }}" class="v-btn-ghost text-xs text-rose-600">{{ __('Delete') }}</button>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-card :title="__('Invite a user')" :description="__('A strong password is generated; share it securely.')">
            <form wire:submit="create" class="space-y-3">
                <x-field :label="__('Name')" error="name"><input type="text" wire:model="name" class="v-input" required></x-field>
                <x-field :label="__('Email')" error="email"><input type="email" wire:model="email" class="v-input" required></x-field>
                <div class="grid grid-cols-2 gap-3">
                    <x-field :label="__('Role')" error="role">
                        <select wire:model="role" class="v-input">
                            @foreach ($roles as $r)
                                <option value="{{ $r->value }}">{{ $r->label() }}</option>
                            @endforeach
                        </select>
                    </x-field>
                    <x-field :label="__('Language')" error="locale">
                        <select wire:model="locale" class="v-input">
                            <option value="en">English</option>
                            <option value="de">Deutsch</option>
                        </select>
                    </x-field>
                </div>
                <button class="v-btn-primary w-full">{{ __('Create user') }}</button>
            </form>
        </x-card>
    </div>
</div>
