@props(['label', 'for' => null, 'error' => null, 'hint' => null])
<div {{ $attributes }}>
    <label class="v-label" @if ($for) for="{{ $for }}" @endif>{{ $label }}</label>
    {{ $slot }}
    @if ($hint)
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</p>
    @endif
    @if ($error)
        @error($error)
            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
        @enderror
    @endif
</div>
