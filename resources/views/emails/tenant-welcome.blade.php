@extends('emails.layout')

@section('title', 'Welcome to '.$brand['name'])
@section('preheader', 'Your '.$tenant->name.' workspace is live. Here is how to get set up in minutes.')
@section('eyebrow', 'Workspace activated')
@section('heading', 'Welcome to '.$brand['name'].', '.$tenant->name.'!')

@section('content')
    <p>
        Your email is verified and your workspace is live. You can sign in now and start
        setting things up for your team and borrowers.
    </p>

    <x-email.details :rows="[
        'Workspace' => $tenant->name,
        'Workspace ID' => $tenant->usesSharedPortal() ? $tenant->slug : null,
        'Sign-in email' => $tenant->admin_email,
        'Plan' => $tenant->plan ? ucfirst($tenant->plan) : null,
        'Trial ends' => $tenant->trial_ends_at?->format('j F Y'),
    ]" />

    <x-email.button :href="$loginUrl">Sign in to your workspace</x-email.button>

    <p style="font-size: 16px; font-weight: 700; color: #101828; margin: 8px 0 16px;">Get set up in four steps</p>

    <x-email.steps :steps="[
        ['title' => 'Add your branding', 'text' => 'Upload your logo and pick your colours — your borrower emails and portal will use them.'],
        ['title' => 'Create your loan products', 'text' => 'Set interest, fees, and repayment schedules for each product you offer.'],
        ['title' => 'Invite your team', 'text' => 'Add loan officers and managers with the right roles and permissions.'],
        ['title' => 'Add your first borrower', 'text' => 'Capture KYC, run an application, and disburse.'],
    ]" />

    @if ($tenant->usesSharedPortal())
        <x-email.panel title="Signing in">
            On the sign-in page, enter your workspace ID <strong>{{ $tenant->slug }}</strong> together
            with your email and password.
        </x-email.panel>
    @endif
@endsection
