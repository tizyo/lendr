@extends('emails.layout')

@section('title', $headline)

@section('header_sub')
    Loan Notification — {{ $loanNumber }}
@endsection

@section('content')
    <h2>{{ $headline }}</h2>
    <p>Hi {{ $borrowerName }},</p>
    <p>{!! $body !!}</p>

    <div class="highlight {{ $isAlert ? 'alert' : '' }}">
        <strong>Loan Reference:</strong> {{ $loanNumber }}
        @if (!empty($context['amount_paid']))
        <br><strong>Amount Paid:</strong> ZMW {{ number_format((float) $context['amount_paid'], 2) }}
        @endif
        @if (!empty($context['due_date']))
        <br><strong>Due Date:</strong> {{ $context['due_date'] }}
        @endif
    </div>

    <p>
        <a href="{{ $ctaUrl }}" class="btn {{ $isAlert ? 'alert' : '' }}">{{ $ctaLabel }}</a>
    </p>

    <p style="font-size:13px; color:#6B7280;">
        If you have any questions, please contact your loan officer or visit our support centre.
    </p>
@endsection
