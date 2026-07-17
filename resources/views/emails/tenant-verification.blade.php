@extends('emails.layout')

@section('title', 'Verify your email — ' . ($branding['company_name'] ?? config('app.name')))

@section('header_sub')
    One more step to activate your workspace
@endsection

@push('styles')
<style>
    .org-badge { display: inline-block; background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; border-radius: 8px; padding: 8px 16px; font-size: 14px; font-weight: 600; margin-bottom: 24px; }
    .fallback { font-size: 12px; color: #9ca3af; line-height: 1.6; }
    .fallback a { color: #059669; word-break: break-all; }
    .notice { background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; padding: 14px 16px; margin-top: 20px; }
    .notice p { font-size: 13px; color: #92400e; line-height: 1.5; }
</style>
@endpush

@section('content')
    <p style="font-size:16px; font-weight:600; color:#111827; margin-bottom:12px;">Hi {{ $tenant->name }} team,</p>
    <p style="font-size:14px; color:#4b5563; line-height:1.7; margin-bottom:16px;">
        Thanks for registering on <strong>{{ $branding['company_name'] ?? config('app.name') }}</strong>!
        To activate your workspace, please verify the admin email address associated with your account.
    </p>

    <div style="text-align:center;">
        <span class="org-badge">{{ $tenant->name }}</span>
    </div>

    <div style="text-align:center; margin: 28px 0;">
        <a href="{{ $verificationUrl }}" class="btn">Verify Email Address</a>
    </div>

    <hr style="border:none; border-top:1px solid #f3f4f6; margin: 24px 0;" />

    <p class="fallback">
        If the button above doesn't work, copy and paste this link into your browser:<br />
        <a href="{{ $verificationUrl }}">{{ $verificationUrl }}</a>
    </p>

    <div class="notice">
        <p>
            <strong>This link expires in 24 hours.</strong> If you did not create a
            {{ $branding['company_name'] ?? config('app.name') }} account, you can safely ignore this email.
        </p>
    </div>
@endsection
