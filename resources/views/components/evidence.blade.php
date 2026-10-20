@props(['evidence' => []])
{{-- Renders a finding's evidence: typed values are formatted for the reader's locale, lists become tables. --}}
@php
    $scalars = [];
    $lists = [];
    foreach ($evidence ?? [] as $key => $value) {
        if (is_array($value) && ! isset($value['type'])) {
            $lists[$key] = $value;
        } else {
            $scalars[$key] = $value;
        }
    }
@endphp
@if ($scalars)
    <dl class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
        @foreach ($scalars as $key => $value)
            <div class="flex flex-col border-b border-slate-100 pb-1.5 dark:border-slate-800">
                <dt class="text-xs text-slate-500 dark:text-slate-400">{{ __('evidence.'.$key) }}</dt>
                <dd class="break-words font-medium">{{ \App\Support\Format::param($value) }}</dd>
            </div>
        @endforeach
    </dl>
@endif
@foreach ($lists as $key => $rows)
    <div class="mt-4">
        <div class="v-label">{{ __('evidence.'.$key) }}</div>
        @if (count($rows) === 0)
            <p class="text-sm text-slate-500">—</p>
        @elseif (is_array(reset($rows)))
            <div class="overflow-x-auto rounded-md border border-slate-200 dark:border-slate-800">
                <table class="v-table">
                    <thead class="bg-slate-50 dark:bg-slate-800/50">
                        <tr>
                            @foreach (array_keys(reset($rows)) as $column)
                                <th>{{ __('evidence.'.$column) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($rows as $row)
                            <tr>
                                @foreach ($row as $cell)
                                    <td class="break-all">{{ \App\Support\Format::param($cell) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <ul class="list-inside list-disc text-sm">
                @foreach ($rows as $row)
                    <li>{{ $row }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endforeach
