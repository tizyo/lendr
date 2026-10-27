@extends('emails.layout')

@section('title', 'Welcome to '.$brand['name'])
@section('preheader', 'Your '.$brand['name'].' account is ready — your sign-in details are inside.')
@section('eyebrow', 'Your account is ready')
@section('heading', 'Welcome aboard, '.\Illuminate\Support\Str::before(trim($staff->name), ' ').'!')

@section('content')
    <p>
        Your staff account on <strong>{{ $brand['name'] }}</strong> has been created.
        Use the details below to sign in.
    </p>

    <x-email.details :mono="['Username', 'Temporary password']" :rows="[
        'Email' => $staff->email,
        'Username' => $staff->username,
        'Temporary password' => $temporaryPassword,
        'Role' => ucwords(str_replace('_', ' ', $staff->role?->value ?? 'Staff')),
    ]" />

    <x-email.button :href="url('/login')">Sign in now</x-email.button>

    <x-email.panel variant="warning" title="Change your password on first sign-in">
        For your security you'll be asked to set a new password the first time you sign in.
        Never share your credentials — {{ $brand['name'] }} staff will never ask for them.
    </x-email.panel>
@endsection
