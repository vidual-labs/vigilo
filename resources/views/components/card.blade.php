@props(['title' => null, 'description' => null])
<section {{ $attributes->merge(['class' => 'v-card']) }}>
    @if ($title || isset($actions))
        <div class="flex flex-wrap items-start justify-between gap-2 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
            <div>
                @if ($title)
                    <h2 class="text-sm font-semibold">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $description }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif
    <div class="p-4">{{ $slot }}</div>
</section>
