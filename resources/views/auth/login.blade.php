<x-layouts.guest :title="__('Sign in')">
    <h1 class="mb-4 text-lg font-semibold">{{ __('Sign in') }}</h1>
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <x-field :label="__('Email')" for="email" error="email">
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="v-input">
        </x-field>
        <x-field :label="__('Password')" for="password">
            <input id="password" name="password" type="password" required autocomplete="current-password" class="v-input">
        </x-field>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 dark:border-slate-700" @checked(old('remember'))>
            {{ __('Remember me') }}
        </label>
        <button class="v-btn-primary w-full">{{ __('Sign in') }}</button>
    </form>
    <a href="{{ route('password.request') }}" class="mt-4 block text-center text-sm text-slate-500 hover:underline">{{ __('Forgot your password?') }}</a>
</x-layouts.guest>
