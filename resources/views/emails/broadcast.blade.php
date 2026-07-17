@extends('emails.layout')

@section('title', $subject)

@section('header_sub')
    Message from your lender
@endsection

@section('content')
    <p>Hi {{ $borrowerName }},</p>
    <div class="message-box">{{ $message }}</div>
    <p style="font-size:13px; color:#6B7280;">
        If you have any questions, please contact your loan officer or visit our support centre.
    </p>
@endsection
