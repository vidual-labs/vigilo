@props(['title', 'text' => null])
<div class="px-4 py-10 text-center">
    <div class="text-sm font-medium">{{ $title }}</div>
    @if ($text)
        <p class="mx-auto mt-1 max-w-md text-sm text-slate-500 dark:text-slate-400">{{ $text }}</p>
    @endif
    {{ $slot }}
</div>
