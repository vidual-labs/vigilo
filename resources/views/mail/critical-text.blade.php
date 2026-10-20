{{ trans_choice('New critical finding|New critical findings', $findings->count()) }}

@foreach ($findings as $finding)
[{{ $finding->severity->label() }}] {{ $finding->client?->name }}: {{ $finding->title() }}
  {{ route('findings.show', $finding) }}
@endforeach
