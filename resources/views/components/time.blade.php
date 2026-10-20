@props(['at', 'empty' => '—'])
@if ($at)
    <time datetime="{{ $at->toIso8601String() }}" title="{{ $at->setTimezone(config('vigilo.timezone'))->isoFormat('LLLL') }}" {{ $attributes }}>
        {{ $at->locale(app()->getLocale())->diffForHumans() }}
    </time>
@else
    <span {{ $attributes->merge(['class' => 'text-slate-400']) }}>{{ $empty }}</span>
@endif
