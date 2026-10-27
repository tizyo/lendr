@extends('emails.layout')

@section('title', $headline)
@section('preheader', strip_tags($body))
@section('eyebrow', 'Loan '.$loanNumber)
@section('heading', $headline)

@section('content')
    <p>Hi {{ $borrowerName }},</p>
    <p>{!! $body !!}</p>

    <x-email.details :rows="[
        'Loan reference' => $loanNumber,
        'Amount paid' => ! empty($context['amount_paid']) ? 'ZMW '.number_format((float) $context['amount_paid'], 2) : null,
        'Outstanding balance' => isset($context['outstanding']) && $context['outstanding'] !== '' ? 'ZMW '.number_format((float) $context['outstanding'], 2) : null,
        'Amount due' => ! empty($context['amount_due']) ? 'ZMW '.number_format((float) $context['amount_due'], 2) : null,
        'Due date' => $context['due_date'] ?? null,
    ]" />

    @if ($ctaUrl)
        <x-email.button :href="$ctaUrl" :variant="$isAlert ? 'danger' : 'primary'">{{ $ctaLabel }}</x-email.button>
    @endif

    <p style="font-size: 13px; color: #667085;">
        Questions about your loan? Reply to this email or contact your loan officer
        @if (! empty($brand['phone'])) on <strong>{{ $brand['phone'] }}</strong>@endif.
    </p>
@endsection
