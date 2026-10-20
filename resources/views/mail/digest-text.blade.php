{{ __('Daily digest') }}

@php($total = array_sum($digest['counts']))
@if ($total === 0)
{{ __('All clear: no open findings across :count clients.', ['count' => $digest['clients_total']]) }}
@else
@foreach ($digest['groups'] as $severity => $findings)
@foreach ($findings as $finding)
[{{ $finding->severity->label() }}] {{ $finding->client?->name }}: {{ $finding->title() }}
  {{ route('findings.show', $finding) }}
@endforeach
@endforeach
@endif

{{ route('findings.index') }}
