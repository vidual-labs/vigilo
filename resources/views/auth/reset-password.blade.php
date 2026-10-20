<x-layouts.guest :title="__('Choose a new password')">
    <h1 class="mb-4 text-lg font-semibold">{{ __('Choose a new password') }}</h1>
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-field :label="__('Email')" for="email" error="email">
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required class="v-input">
        </x-field>
        <x-field :label="__('New password')" for="password" error="password" :hint="__('At least 12 characters.')">
            <input id="password" name="password" type="password" required autocomplete="new-password" class="v-input">
        </x-field>
        <x-field :label="__('Confirm password')" for="password_confirmation">
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="v-input">
        </x-field>
        <button class="v-btn-primary w-full">{{ __('Save password') }}</button>
    </form>
</x-layouts.guest>
