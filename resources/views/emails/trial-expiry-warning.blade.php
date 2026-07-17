@extends('emails.layout')

@section('title', 'Trial Ending Soon — ' . ($branding['company_name'] ?? config('app.name')))

@push('styles')
<style>
    .days-badge { display:inline-block; background:#fef3c7; color:#92400e; font-size:32px; font-weight:900; padding:12px 28px; border-radius:12px; margin:16px 0; }
    .cta-wrap { text-align:center; margin: 24px 0; }
    .cta-btn { display:inline-block; background:#16a34a; color:#fff !important; font-weight:700; font-size:15px; padding:14px 28px; border-radius:10px; text-decoration:none; }
</style>
@endpush

@section('header_sub')
    {{ $branding['company_name'] ?? config('app.name') }} · Trial Notice
@endsection

@section('content')
    <h2>Trial Ending Soon</h2>
    <p>Hi {{ $tenant->name }},</p>
    <p>Your free trial is ending soon. You have:</p>
    <div style="text-align:center;">
        <span class="days-badge">{{ $daysRemaining }} day{{ $daysRemaining === 1 ? '' : 's' }} left</span>
    </div>
    <p>After your trial ends, access to your workspace will be paused until you upgrade. Your data is safe — we hold it for 30 days after expiry.</p>
    <p><strong>What happens when the trial ends?</strong></p>
    <ul style="padding-left:20px; font-size:15px; line-height:2;">
        <li>Login will be disabled for all staff</li>
        <li>Your data is preserved for 30 days</li>
        <li>Upgrade at any time to restore full access immediately</li>
    </ul>

    <div class="cta-wrap">
        <a href="mailto:{{ $branding['email'] ?? 'sales@lendr.app' }}?subject=Upgrade%20request%20%E2%80%94%20{{ urlencode($tenant->name) }}" class="cta-btn">
            Upgrade Now →
        </a>
    </div>

    <p style="color:#6b7280;font-size:13px;">
        Questions? Contact
        <a href="mailto:{{ $branding['email'] ?? 'support@lendr.app' }}" style="color:#16a34a;">{{ $branding['email'] ?? 'support@lendr.app' }}</a>
    </p>
@endsection
