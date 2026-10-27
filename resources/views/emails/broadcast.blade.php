@extends('emails.layout')

@section('title', $subject)
@section('preheader', \Illuminate\Support\Str::limit($body, 120))
@section('eyebrow', 'Message from '.$brand['name'])
@section('heading', $subject)

@section('content')
    <p>Hi {{ $borrowerName }},</p>

    <x-email.panel>
        <div style="font-size: 15px; line-height: 1.7; color: #101828; white-space: pre-line;">{{ $body }}</div>
    </x-email.panel>

    <p style="font-size: 13px; color: #667085;">
        Questions? Reply to this email or contact your loan officer
        @if (! empty($brand['phone'])) on <strong>{{ $brand['phone'] }}</strong>@endif.
    </p>
@endsection
