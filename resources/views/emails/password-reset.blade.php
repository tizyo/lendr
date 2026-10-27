@extends('emails.layout')

@section('title', 'Reset your password — '.$brand['name'])
@section('preheader', 'Use this link to choose a new password for your '.$brand['name'].' account.')
@section('eyebrow', 'Account security')
@section('heading', 'Reset your password')

@section('content')
    <p>Hi{{ $name ? ' '.$name : '' }},</p>
    <p>
        We received a request to reset the password for <strong>{{ $email }}</strong>
        on {{ $brand['name'] }}. Click the button below to choose a new one.
    </p>

    <x-email.button :href="$url">Choose a new password</x-email.button>

    <x-email.fallback-link :url="$url" />

    <x-email.panel variant="neutral" title="Didn't ask for this?">
        This link expires in <strong>{{ $expireMinutes }} minutes</strong> and can only be used once.
        If you didn't request a reset, you can ignore this email — your password won't change.
    </x-email.panel>
@endsection
