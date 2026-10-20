@include('mail.partials.layout-start')
@php($total = array_sum($digest['counts']))
<h1 style="font-size:18px;margin:0 0 6px;">{{ __('Daily digest') }}</h1>
<p style="margin:0 0 16px;font-size:14px;color:#334155;">
    @if ($total === 0)
        {{ __('All clear: no open findings across :count clients.', ['count' => $digest['clients_total']]) }}
    @else
        {{ __(':critical critical, :warning warnings and :info info across :clients of :total clients. :new new since yesterday.', [
            'critical' => $digest['counts']['critical'], 'warning' => $digest['counts']['warning'], 'info' => $digest['counts']['info'],
            'clients' => $digest['clients_with_findings'], 'total' => $digest['clients_total'], 'new' => $digest['new'],
        ]) }}
    @endif
</p>
@foreach ($digest['groups'] as $severity => $findings)
    @if (count($findings) > 0)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:12px;">
            @foreach ($findings as $finding)
                @include('mail.partials.finding-row', ['finding' => $finding, 'isNew' => $finding->isNewSince($digest['since'])])
            @endforeach
        </table>
        @if ($digest['more'][$severity] > 0)
            <p style="font-size:12px;color:#64748b;">{{ __('… and :count more.', ['count' => $digest['more'][$severity]]) }}</p>
        @endif
    @endif
@endforeach
<p style="margin:18px 0 0;">
    <a href="{{ route('findings.index') }}" style="display:inline-block;background:#0f766e;color:#ffffff;padding:9px 16px;border-radius:6px;text-decoration:none;font-size:14px;font-weight:600;">{{ __('Open the findings inbox') }}</a>
</p>
@include('mail.partials.layout-end')
