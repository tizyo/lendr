@extends('emails.layout')

@section('title', 'Verify your email — '.$brand['name'])
@section('preheader', 'One click to activate your '.$tenant->name.' workspace on '.$brand['name'].'.')
@section('eyebrow', 'Almost there')
@section('heading', 'Confirm your email to activate your workspace')

@section('content')
    <p>Hi {{ $tenant->name }} team,</p>
    <p>
        Thanks for signing up for <strong>{{ $brand['name'] }}</strong>. Confirm the email address
        on your account and your workspace will be ready to use.
    </p>

    <x-email.details :rows="[
        'Workspace' => $tenant->name,
        'Workspace ID' => $tenant->slug,
        'Admin email' => $tenant->admin_email,
        'Plan' => $tenant->plan ? ucfirst($tenant->plan) : null,
    ]" />

    <x-email.button :href="$verificationUrl">Verify email address</x-email.button>

    <x-email.fallback-link :url="$verificationUrl" />

    <x-email.panel variant="neutral">
        This link expires in <strong>24 hours</strong>. If you didn't create a {{ $brand['name'] }}
        account, you can safely ignore this email.
    </x-email.panel>
@endsection
