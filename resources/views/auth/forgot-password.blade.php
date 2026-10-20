<x-layouts.guest :title="__('Reset password')">
    <h1 class="mb-2 text-lg font-semibold">{{ __('Reset password') }}</h1>
    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ __('Enter your email address and we will send you a link to choose a new password.') }}</p>
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <x-field :label="__('Email')" for="email" error="email">
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="v-input">
        </x-field>
        <button class="v-btn-primary w-full">{{ __('Send reset link') }}</button>
    </form>
    <a href="{{ route('login') }}" class="mt-4 block text-center text-sm text-slate-500 hover:underline">{{ __('Back to sign in') }}</a>
</x-layouts.guest>
