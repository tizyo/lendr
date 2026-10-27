@extends('emails.layout')

@php($supportEmail = $brand['email'] ?: 'support@lendr.app')
@php($upgradeUrl = 'mailto:'.$supportEmail.'?subject='.rawurlencode('Upgrade request — '.$tenant->name))

@section('title', 'Your trial has ended — '.$brand['name'])
@section('preheader', 'Your '.$tenant->name.' workspace is paused. Your data is safe for 30 days — upgrade to restore access.')
@section('eyebrow', 'Account notice')
@section('heading', 'Your free trial has ended')

@section('content')
    <p>Hi {{ $tenant->name }},</p>
    <p>Your {{ $brand['name'] }} trial has ended and your workspace is now paused.</p>

    <x-email.panel variant="success" title="Your data is safe">
        We keep everything for <strong>30 days</strong>. Upgrade any time in that window and
        your team picks up exactly where they left off — no data loss, no setup.
    </x-email.panel>

    <x-email.button :href="$upgradeUrl">Upgrade &amp; restore access</x-email.button>

    <x-email.button :href="'mailto:'.$supportEmail" variant="secondary">Talk to support</x-email.button>

    <p style="font-size: 12px; color: #98A2B3;">
        No longer need your workspace? No action is required — data is deleted automatically after 30 days.
    </p>
@endsection
