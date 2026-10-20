@php
    $colors = ['critical' => ['#ffe4e6', '#9f1239'], 'warning' => ['#fef3c7', '#78350f'], 'info' => ['#e0f2fe', '#075985']];
    [$bg, $fg] = $colors[$finding->severity->value];
@endphp
<tr>
    <td style="padding:10px 0;border-bottom:1px solid #f1f5f9;vertical-align:top;">
        <span style="display:inline-block;padding:1px 8px;border-radius:999px;font-size:11px;font-weight:600;background:{{ $bg }};color:{{ $fg }};">{{ $finding->severity->label() }}</span>
        @if ($isNew ?? false)
            <span style="display:inline-block;padding:1px 8px;border-radius:999px;font-size:11px;font-weight:600;background:#ccfbf1;color:#115e59;">{{ __('New') }}</span>
        @endif
        @if ($finding->status === \App\Domain\Findings\Enums\FindingStatus::Acknowledged)
            <span style="display:inline-block;padding:1px 8px;border-radius:999px;font-size:11px;background:#ede9fe;color:#5b21b6;">{{ __('Acknowledged') }}</span>
        @endif
        <span style="font-size:12px;color:#64748b;">{{ $finding->client?->name }} · {{ $finding->checkLabel() }}</span>
        <div style="margin-top:4px;font-size:14px;">
            <a href="{{ route('findings.show', $finding) }}" style="color:#0f172a;text-decoration:none;font-weight:600;">{{ $finding->title() }}</a>
        </div>
        <div style="font-size:12px;color:#64748b;margin-top:2px;">{{ __('Detected') }} {{ $finding->opened_at->locale(app()->getLocale())->diffForHumans() }}</div>
    </td>
</tr>
