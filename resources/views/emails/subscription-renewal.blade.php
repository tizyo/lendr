@extends('emails.layout')

@php($supportEmail = $brand['email'] ?: config('lendr.support_email'))
@php($planLabel = ucfirst($subscription->plan))
{{-- Paid plans live on their own subdomain; route() here would point at the central host. --}}
@php($renewUrl = $tenant->staffUseCentralHost() ? $tenant->loginUrl() : $tenant->domainUrl().'/billing?plan='.$subscription->plan)
@php($ended = $stage === 'ended')

@section('title', ($ended ? 'Your plan has ended' : 'Your plan is ending soon').' | '.$brand['name'])
@section('preheader', $ended
    ? "{$tenant->name} is back on the Starter plan. Renew {$planLabel} to switch its features back on."
    : "{$tenant->name}'s {$planLabel} plan ends on ".$subscription->ends_at?->format('d M Y').'. Renew to keep every feature.')
@section('eyebrow', 'Subscription')
@section('heading', $ended ? "Your {$planLabel} plan has ended" : "Your {$planLabel} plan ends soon")

@section('content')
    <p>Hi {{ $tenant->name }},</p>

    @if($ended)
        <p>Your {{ $planLabel }} subscription ended on {{ $subscription->ends_at?->format('d M Y') }} and wasn't renewed, so your workspace has moved to the Starter plan.</p>
        <p>Your borrowers, loans and payments are untouched. Features that need {{ $planLabel }} are switched off until you renew.</p>
    @else
        <p>Your {{ $planLabel }} subscription ends on <strong>{{ $subscription->ends_at?->format('d M Y') }}</strong>. Renew before then to keep every {{ $planLabel }} feature running.</p>
        <x-email.stat :value="$daysRemaining.' day'.($daysRemaining === 1 ? '' : 's')" label="until your plan ends" variant="warning" />
    @endif

    <x-email.button :href="$renewUrl">Renew {{ $planLabel }}</x-email.button>

    <p style="font-size: 13px; color: #667085;">
        Questions? Write to <a href="mailto:{{ $supportEmail }}" style="color: {{ $brand['accent'] }};">{{ $supportEmail }}</a>.
    </p>
@endsection
