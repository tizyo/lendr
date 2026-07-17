@extends('emails.layout')

@section('title', "You're invited to join " . $orgName)

@section('header_sub')
    Set up your account to get started
@endsection

@push('styles')
<style>
    .org-badge { display: inline-block; background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; border-radius: 8px; padding: 8px 16px; font-size: 14px; font-weight: 600; margin-bottom: 8px; }
    .info-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px 20px; margin: 20px 0; }
    .info-row { display: flex; justify-content: space-between; padding: 5px 0; font-size: 13px; }
    .info-label { color: #6b7280; }
    .info-value { color: #111827; font-weight: 600; }
    .fallback { font-size: 12px; color: #9ca3af; line-height: 1.6; }
    .fallback a { color: #059669; word-break: break-all; }
    .notice { background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; padding: 14px 16px; margin-top: 20px; }
    .notice p { font-size: 13px; color: #92400e; line-height: 1.5; }
</style>
@endpush

@section('content')
    <p style="font-size:16px; font-weight:600; color:#111827; margin-bottom:12px;">Hi {{ $staff->name }},</p>
    <p style="font-size:14px; color:#4b5563; line-height:1.7; margin-bottom:16px;">
        You have been added as a staff member on <strong>{{ $branding['company_name'] ?? config('app.name') }}</strong>
        by your organisation. Click the button below to activate your account and set your password.
    </p>

    <div style="text-align:center; margin-bottom: 8px;">
        <span class="org-badge">{{ $orgName }}</span>
    </div>

    <div class="info-box">
        <div class="info-row">
            <span class="info-label">Your name</span>
            <span class="info-value">{{ $staff->name }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Email</span>
            <span class="info-value">{{ $staff->email }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Role</span>
            <span class="info-value">{{ ucwords(str_replace('_', ' ', $staff->role?->value ?? 'Staff')) }}</span>
        </div>
    </div>

    <div style="text-align:center; margin: 28px 0;">
        <a href="{{ $invitationUrl }}" class="btn">Activate My Account</a>
    </div>

    <hr style="border:none; border-top:1px solid #f3f4f6; margin: 24px 0;" />

    <p class="fallback">
        If the button above doesn't work, copy and paste this link into your browser:<br />
        <a href="{{ $invitationUrl }}">{{ $invitationUrl }}</a>
    </p>

    <div class="notice">
        <p>
            <strong>This invitation link expires in 48 hours.</strong> If you did not expect this
            invitation, you can safely ignore this email.
        </p>
    </div>
@endsection
