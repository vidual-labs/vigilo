@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' · ' : '' }}vigilo</title>
    <x-theme-script />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
<div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
    <div class="mb-6 flex items-center gap-2 text-xl font-semibold tracking-tight">
        <x-logo size="h-9 w-9" />
        vigilo
    </div>
    <div class="v-card w-full max-w-sm p-6">
        <x-flash />
        {{ $slot }}
    </div>
    <form method="POST" action="{{ route('locale') }}" class="mt-4">
        @csrf
        <input type="hidden" name="locale" value="{{ app()->getLocale() === 'de' ? 'en' : 'de' }}">
        <button class="text-xs text-slate-500 hover:underline">{{ app()->getLocale() === 'de' ? 'English' : 'Deutsch' }}</button>
    </form>
</div>
</body>
</html>
