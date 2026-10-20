@props(['size' => 'h-7 w-7'])
{{-- An eye: vigilo is Latin for "I keep watch". --}}
<svg {{ $attributes->merge(['class' => $size]) }} viewBox="0 0 32 32" fill="none" aria-hidden="true">
    <rect width="32" height="32" rx="8" class="fill-teal-700 dark:fill-teal-600"/>
    <path d="M5 16c2.8-4.6 6.6-7 11-7s8.2 2.4 11 7c-2.8 4.6-6.6 7-11 7s-8.2-2.4-11-7Z" stroke="white" stroke-width="2" stroke-linejoin="round"/>
    <circle cx="16" cy="16" r="3.6" fill="white"/>
</svg>
