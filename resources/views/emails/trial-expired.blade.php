@extends('emails.layout')

@section('title', 'Trial Expired — ' . ($branding['company_name'] ?? config('app.name')))

@push('styles')
<style>
    .cta-wrap { text-align:center; margin: 24px 0; }
    .cta-btn { display:inline-block; background:#16a34a; color:#fff !important; font-weight:700; font-size:15px; padding:14px 28px; border-radius:10px; text-decoration:none; }
    .cta-sec { display:inline-block; margin-top:12px; border:1px solid #e5e7eb; color:#374151 !important; font-weight:600; font-size:14px; padding:12px 28px; border-radius:10px; text-decoration:none; }
</style>
@endpush

@section('header_sub')
    {{ $branding['company_name'] ?? config('app.name') }} · Account Notice
@endsection

@section('content')
    <h2 style="color:#DC2626;">Trial Expired</h2>
    <p>Hi {{ $tenant->name }},</p>
    <p>Your 14-day free trial has ended. Your workspace is currently paused.</p>
    <p><strong>Your data is safe.</strong> We keep everything for 30 days. Upgrade any time to restore full access immediately — no data loss, no setup needed.</p>

    <div class="cta-wrap">
        <a href="mailto:{{ $branding['email'] ?? 'sales@lendr.app' }}?subject=Upgrade%20request%20%E2%80%94%20{{ urlencode($tenant->name) }}" class="cta-btn">
            Upgrade &amp; Restore Access →
        </a>
        <br>
        <a href="mailto:{{ $branding['email'] ?? 'support@lendr.app' }}" class="cta-sec">Contact Support</a>
    </div>

    <p style="color:#9ca3af;font-size:12px;margin-top:24px;">
        If you no longer need your account, no action is required. Data is automatically deleted after 30 days of inactivity.
    </p>
@endsection
