@extends('emails.layout')

@php($supportEmail = $brand['email'] ?: 'support@lendr.app')
@php($upgradeUrl = 'mailto:'.$supportEmail.'?subject='.rawurlencode('Upgrade request — '.$tenant->name))

@section('title', 'Your trial is ending soon — '.$brand['name'])
@section('preheader', 'Your '.$tenant->name.' trial ends in '.$daysRemaining.' day'.($daysRemaining === 1 ? '' : 's').'. Upgrade to keep your workspace running.')
@section('eyebrow', 'Trial notice')
@section('heading', 'Your free trial is ending soon')

@section('content')
    <p>Hi {{ $tenant->name }},</p>
    <p>Your {{ $brand['name'] }} trial is nearly over. Upgrade now to keep your team working without interruption.</p>

    <x-email.stat :value="$daysRemaining.' day'.($daysRemaining === 1 ? '' : 's')" label="left on your free trial" variant="warning" />

    <p style="font-weight: 600; color: #101828; margin-bottom: 12px;">When the trial ends</p>
    <x-email.steps :steps="[
        ['title' => 'Sign-in is paused for all staff', 'text' => 'Borrower data and loan books stay exactly as they are.'],
        ['title' => 'Your data is kept safe for 30 days', 'text' => 'Nothing is deleted while you decide.'],
        ['title' => 'Upgrade any time to restore access', 'text' => 'Everything comes back instantly — no setup needed.'],
    ]" />

    <x-email.button :href="$upgradeUrl">Upgrade now</x-email.button>

    <p style="font-size: 13px; color: #667085;">
        Questions? Write to <a href="mailto:{{ $supportEmail }}" style="color: {{ $brand['accent'] }};">{{ $supportEmail }}</a>.
    </p>
@endsection
