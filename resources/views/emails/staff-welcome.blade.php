@extends('emails.layout')

@section('title', 'Welcome to ' . ($branding['company_name'] ?? config('app.name')))

@section('header_sub')
    Your account is ready
@endsection

@push('styles')
<style>
    .credentials { background: #F3F4F6; border-radius: 6px; padding: 16px 20px; margin: 24px 0; }
    .credentials p { margin: 4px 0; font-size: 14px; }
    .credentials strong { display: inline-block; width: 120px; color: #6B7280; }
    .credentials code { font-family: monospace; color: #111827; font-size: 15px; }
</style>
@endpush

@section('content')
    <h2>Welcome, {{ $staff->name }}!</h2>

    <p>
        Your staff account has been created on <strong>{{ $branding['company_name'] ?? config('app.name') }}</strong>.
        You can log in with the credentials below.
    </p>

    <div class="credentials">
        <p><strong>Email:</strong> <code>{{ $staff->email }}</code></p>
        @if ($staff->username)
        <p><strong>Username:</strong> <code>{{ $staff->username }}</code></p>
        @endif
        <p><strong>Password:</strong> <code>{{ $temporaryPassword }}</code></p>
        <p><strong>Role:</strong> <code>{{ $staff->role?->value }}</code></p>
    </div>

    <p>
        For security, you will be prompted to change your password on first login.
        Please keep your credentials safe and do not share them.
    </p>

    <a href="{{ url('/login') }}" class="btn">Log in Now</a>

    <p style="font-size:13px; color:#6B7280;">
        If you did not expect this email, please contact your administrator immediately.
    </p>
@endsection
