@props(['platform'])
@php
    $classes = $platform === \App\Enums\Platform::Google
        ? 'bg-blue-50 text-blue-800 ring-1 ring-blue-200 dark:bg-blue-950 dark:text-blue-200 dark:ring-blue-800'
        : 'bg-indigo-50 text-indigo-800 ring-1 ring-indigo-200 dark:bg-indigo-950 dark:text-indigo-200 dark:ring-indigo-800';
@endphp
<span {{ $attributes->merge(['class' => 'v-badge '.$classes]) }}>{{ $platform->label() }}</span>
