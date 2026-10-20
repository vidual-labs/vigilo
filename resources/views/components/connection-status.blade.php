@props(['status'])
<span {{ $attributes->merge(['class' => 'v-badge '.$status->badgeClasses()]) }}>{{ $status->label() }}</span>
