@if ($paginator->hasPages())
    <nav class="flex items-center justify-between gap-2 text-sm" role="navigation">
        <span class="text-slate-500">{{ __(':from–:to of :total', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()]) }}</span>
        <div class="flex gap-1">
            <button type="button" class="v-btn-secondary" wire:click="previousPage" @disabled($paginator->onFirstPage())>{{ __('Previous') }}</button>
            <button type="button" class="v-btn-secondary" wire:click="nextPage" @disabled(! $paginator->hasMorePages())>{{ __('Next') }}</button>
        </div>
    </nav>
@endif
