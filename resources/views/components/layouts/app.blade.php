@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' · ' : '' }}vigilo</title>
    <x-theme-script />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
@php
    $user = auth()->user();
    $openCritical = \App\Models\Finding::query()->visibleTo($user)->where('status', 'open')->where('severity', 'critical')->count();
    $nav = [
        ['findings.index', __('Findings'), 'findings.*'],
        ['clients.index', __('Clients'), 'clients.*'],
        ['connections.index', __('Connections'), 'connections.*'],
        ['checks.index', __('Checks'), 'checks.*'],
        ['notifications.index', __('Notifications'), 'notifications.*'],
    ];
    if ($user->can('operate')) {
        $nav[] = ['users.index', __('Users'), 'users.*'];
    }
@endphp
<div x-data="{ menu: false }" class="flex min-h-screen flex-col">
    <header class="border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div class="mx-auto flex max-w-7xl items-center gap-4 px-4 py-2.5 sm:px-6">
            <a href="{{ route('findings.index') }}" class="flex items-center gap-2 font-semibold tracking-tight">
                <x-logo />
                <span class="text-lg">vigilo</span>
            </a>

            <nav class="hidden flex-1 items-center gap-x-1 md:flex">
                @foreach ($nav as [$route, $label, $pattern])
                    <a href="{{ route($route) }}" wire:navigate
                       @class([
                           'rounded-md px-2.5 py-1.5 text-sm font-medium',
                           'bg-slate-100 text-slate-900 dark:bg-slate-800 dark:text-white' => request()->routeIs($pattern),
                           'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' => ! request()->routeIs($pattern),
                       ])>
                        {{ $label }}
                        @if ($route === 'findings.index' && $openCritical > 0)
                            <span class="v-badge ml-1 bg-rose-600 px-1.5 text-white">{{ $openCritical }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>

            <div class="ml-auto flex items-center gap-x-1.5">
                @if (config('vigilo.mock_mode'))
                    <span class="v-badge hidden bg-amber-100 text-amber-900 sm:inline-flex dark:bg-amber-900/40 dark:text-amber-200" title="{{ __('All ad platform, lead and landing-page data is simulated. Set VIGILO_MOCK_MODE=false to go live.') }}">
                        {{ __('Mock mode') }}
                    </span>
                @endif

                <div x-data="{ theme: window.vigiloTheme ? window.vigiloTheme.get() : 'system' }">
                    <button type="button" class="v-btn-ghost" :title="'{{ __('Theme') }}: ' + theme"
                            @click="theme = theme === 'light' ? 'dark' : (theme === 'dark' ? 'system' : 'light'); window.vigiloTheme.set(theme)">
                        <svg x-show="theme === 'light'" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.46 4.95l.7.71a1 1 0 001.42-1.42l-.71-.7a1 1 0 00-1.41 1.41zm2.12-10.6a1 1 0 010 1.41l-.7.71a1 1 0 11-1.42-1.42l.71-.7a1 1 0 011.41 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.46a1 1 0 001.41-1.41l-.7-.71a1 1 0 00-1.42 1.42l.71.7zm1.41 8.49l-.7.71a1 1 0 01-1.42-1.42l.71-.7a1 1 0 011.41 1.41zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"/></svg>
                        <svg x-show="theme === 'dark'" x-cloak class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M17.29 13.29A8 8 0 016.71 2.71a8.003 8.003 0 1010.58 10.58z"/></svg>
                        <svg x-show="theme === 'system'" x-cloak class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-2.22l.12.5.81.8A1 1 0 0113 17H7a1 1 0 01-.7-1.7l.8-.8.12-.5H5a2 2 0 01-2-2V5zm2 0h10v7H5V5z" clip-rule="evenodd"/></svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('locale') }}" class="flex">
                    @csrf
                    <input type="hidden" name="locale" value="{{ app()->getLocale() === 'de' ? 'en' : 'de' }}">
                    <button class="v-btn-ghost text-xs font-semibold uppercase" title="{{ __('Language') }}">
                        {{ app()->getLocale() === 'de' ? 'EN' : 'DE' }}
                    </button>
                </form>

                <div x-data="{ open: false }" class="relative">
                    <button type="button" class="v-btn-ghost" @click="open = !open" @click.outside="open = false">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold uppercase dark:bg-slate-700">
                            {{ mb_substr($user->name, 0, 1) }}
                        </span>
                    </button>
                    <div x-show="open" x-cloak x-transition
                         class="absolute right-0 z-30 mt-1 w-56 rounded-md border border-slate-200 bg-white py-1 text-sm shadow-lg dark:border-slate-700 dark:bg-slate-900">
                        <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-800">
                            <div class="font-medium">{{ $user->name }}</div>
                            <div class="text-xs text-slate-500">{{ $user->email }} · {{ $user->role->label() }}</div>
                        </div>
                        <a href="{{ route('profile') }}" wire:navigate class="block px-3 py-2 hover:bg-slate-50 dark:hover:bg-slate-800">{{ __('Profile') }}</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="block w-full px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-800">{{ __('Sign out') }}</button>
                        </form>
                    </div>
                </div>

                <button type="button" class="v-btn-ghost md:hidden" @click="menu = !menu" aria-label="{{ __('Menu') }}">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 5h14v2H3V5zm0 4h14v2H3V9zm0 4h14v2H3v-2z" clip-rule="evenodd"/></svg>
                </button>
            </div>
        </div>
        <nav x-show="menu" x-cloak class="border-t border-slate-200 px-4 py-2 md:hidden dark:border-slate-800">
            @foreach ($nav as [$route, $label, $pattern])
                <a href="{{ route($route) }}" class="block rounded-md px-2 py-1.5 text-sm {{ request()->routeIs($pattern) ? 'font-semibold' : '' }}">{{ $label }}</a>
            @endforeach
        </nav>
    </header>

    <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6">
        <x-flash />
        {{ $slot }}
    </main>

    <footer class="border-t border-slate-200 py-4 text-center text-xs text-slate-500 dark:border-slate-800">
        vigilo v{{ config('vigilo.version') }} · {{ __('Read-only towards ad platforms') }} · AGPL-3.0-or-later
    </footer>
</div>
@livewireScripts
</body>
</html>
