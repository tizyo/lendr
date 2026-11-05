@extends('emails.layout')

@section('title', $subject)
@section('preheader', \Illuminate\Support\Str::limit($body, 120))
@section('eyebrow', 'Message from '.$brand['name'])
@section('heading', $subject)

@section('content')
    @if ($recipientName)
        <p>Hi {{ $recipientName }},</p>
    @endif

    <div style="font-size: 15px; line-height: 1.7; color: #101828; white-space: pre-line; margin: 0 0 24px;">{{ $body }}</div>

    @if ($buttonLabel && $buttonUrl)
        <x-email.button :href="$buttonUrl">{{ $buttonLabel }}</x-email.button>
    @endif

    <p style="font-size: 13px; color: #667085;">
        @if (! empty($brand['email']))
            Questions? Just reply to this email or write to <a href="mailto:{{ $brand['email'] }}">{{ $brand['email'] }}</a>.
        @else
            Questions? Just reply to this email.
        @endif
    </p>
@endsection
