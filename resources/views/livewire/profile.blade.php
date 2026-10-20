<div>
    <x-page-header :title="__('Profile')" />

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Your details')">
            <form wire:submit="save" class="space-y-3">
                <x-field :label="__('Name')" error="name"><input type="text" wire:model="name" class="v-input" required></x-field>
                <x-field :label="__('Email')" error="email"><input type="email" wire:model="email" class="v-input" required></x-field>
                <x-field :label="__('Language')" error="locale">
                    <select wire:model="locale" class="v-input">
                        <option value="en">English</option>
                        <option value="de">Deutsch</option>
                    </select>
                </x-field>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="receivesDigest" class="rounded border-slate-300 dark:border-slate-700"> {{ __('Send me the daily digest') }}</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="notifyCritical" class="rounded border-slate-300 dark:border-slate-700"> {{ __('Email me new critical findings right away') }}</label>
                <p class="text-xs text-slate-500">{{ __('Light/dark mode is switched with the icon in the header and remembered per browser.') }}</p>
                <button class="v-btn-primary">{{ __('Save') }}</button>
            </form>
        </x-card>

        <x-card :title="__('Password')" class="{{ $mustChange ? 'ring-2 ring-amber-400' : '' }}">
            @if ($mustChange)
                <p class="mb-3 text-sm text-amber-700 dark:text-amber-300">{{ __('You are using a generated password. Choose your own to continue.') }}</p>
            @endif
            <form wire:submit="changePassword" class="space-y-3">
                <x-field :label="__('Current password')" error="currentPassword"><input type="password" wire:model="currentPassword" class="v-input" autocomplete="current-password" required></x-field>
                <x-field :label="__('New password')" error="password" :hint="__('At least 12 characters.')"><input type="password" wire:model="password" class="v-input" autocomplete="new-password" required></x-field>
                <x-field :label="__('Confirm password')" error="passwordConfirmation"><input type="password" wire:model="passwordConfirmation" class="v-input" autocomplete="new-password" required></x-field>
                <button class="v-btn-primary">{{ __('Change password') }}</button>
            </form>
        </x-card>
    </div>
</div>
