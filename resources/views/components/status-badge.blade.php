@props(['status'])
@php
    $classes = match ($status) {
        \App\Domain\Findings\Enums\FindingStatus::Open => 'bg-slate-900 text-white dark:bg-white dark:text-slate-900',
        \App\Domain\Findings\Enums\FindingStatus::Acknowledged => 'bg-violet-100 text-violet-800 dark:bg-violet-900/50 dark:text-violet-200',
        \App\Domain\Findings\Enums\FindingStatus::Snoozed => 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
        \App\Domain\Findings\Enums\FindingStatus::Resolved => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200',
    };
@endphp
<span {{ $attributes->merge(['class' => 'v-badge '.$classes]) }}>{{ $status->label() }}</span>
