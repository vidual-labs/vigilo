@props(['severity'])
<span {{ $attributes->merge(['class' => 'v-badge '.$severity->badgeClasses()]) }}>{{ $severity->label() }}</span>
