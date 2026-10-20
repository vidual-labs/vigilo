@include('mail.partials.layout-start')
<h1 style="font-size:18px;margin:0 0 6px;color:#9f1239;">{{ trans_choice('New critical finding|New critical findings', $findings->count()) }}</h1>
<p style="margin:0 0 12px;font-size:14px;color:#334155;">{{ __('vigilo just detected the following. It will also be in tomorrow\'s digest.') }}</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
    @foreach ($findings as $finding)
        @include('mail.partials.finding-row', ['finding' => $finding, 'isNew' => true])
    @endforeach
</table>
<p style="margin:18px 0 0;">
    <a href="{{ route('findings.index', ['severity' => 'critical']) }}" style="display:inline-block;background:#0f766e;color:#ffffff;padding:9px 16px;border-radius:6px;text-decoration:none;font-size:14px;font-weight:600;">{{ __('Open the findings inbox') }}</a>
</p>
@include('mail.partials.layout-end')
