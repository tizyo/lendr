@extends('emails.layout')

@section('title', "You're invited to join ".$orgName)
@section('preheader', $orgName.' has invited you to their team on '.$brand['name'].'. Activate your account to get started.')
@section('eyebrow', 'Team invitation')
@section('heading', "You're invited to join ".$orgName)

@section('content')
    <p>Hi {{ $staff->name }},</p>
    <p>
        You've been added to the <strong>{{ $orgName }}</strong> team. Activate your account
        and set a password to get started.
    </p>

    <x-email.details :rows="[
        'Name' => $staff->name,
        'Email' => $staff->email,
        'Role' => ucwords(str_replace('_', ' ', $staff->role?->value ?? 'Staff')),
    ]" />

    <x-email.button :href="$invitationUrl">Activate my account</x-email.button>

    <x-email.fallback-link :url="$invitationUrl" />

    <x-email.panel variant="neutral">
        This invitation expires in <strong>48 hours</strong>. If you weren't expecting it,
        you can safely ignore this email.
    </x-email.panel>
@endsection
