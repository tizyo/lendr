@extends('emails.layout')

@section('title', 'Disbursement fund is running low')
@section('preheader', 'Available balance is ZMW '.number_format($balance, 2).' — record a deposit to keep disbursing loans.')
@section('eyebrow', 'Fund alert')
@section('heading', 'Your disbursement fund is running low')

@section('content')
    <p>Hi {{ \Illuminate\Support\Str::before(trim($staff->name), ' ') }},</p>
    <p>
        The available balance in {{ $brand['name'] }}'s disbursement fund has dropped below the alert level
        you set. New loans can't be disbursed beyond what's left.
    </p>

    <x-email.stat :value="'ZMW '.number_format($balance, 2)" label="available to disburse" variant="warning" />

    <x-email.details :rows="[
        'Alert level' => 'ZMW '.number_format($threshold, 2),
        'Shortfall' => 'ZMW '.number_format(max(0, $threshold - $balance), 2),
    ]" />

    <x-email.button :href="$fundsUrl">Record a fund deposit</x-email.button>

    <p style="font-size: 13px; color: #667085;">
        You can change the alert level in Settings → General.
    </p>
@endsection
