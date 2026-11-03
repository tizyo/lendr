@extends('pdf.layout')

@php($cur = $currency ?? 'ZMW')

@section('title', 'Payment Receipt')
@section('ref', 'Receipt '.$payment['receipt_number'])

@section('meta')
  Receipt No. <strong>{{ $payment['receipt_number'] }}</strong><br>
  Payment date <strong>{{ $payment['payment_date'] }}</strong><br>
  <span class="pill pill-green">PAID</span>
@endsection

@section('content')
  <table class="cols" style="margin-top: 14px;">
    <tr>
      <td>
        <div class="section" style="margin-top: 0;">Received from</div>
        <div class="bold" style="font-size: 11px; color: #111827;">{{ $payment['borrower_name'] }}</div>
        <div class="muted">Borrower No. {{ $payment['borrower_number'] }}</div>
      </td>
      <td class="gap"></td>
      <td>
        <div class="section" style="margin-top: 0;">Payment for</div>
        <div class="bold" style="font-size: 11px; color: #111827;">Loan {{ $payment['loan_number'] }}</div>
        <div class="muted">{{ $payment['loan_type'] }}</div>
      </td>
    </tr>
  </table>

  <div class="section">Allocation</div>
  <table class="grid">
    <thead>
      <tr><th>Description</th><th class="num" style="width: 30%;">Amount ({{ $cur }})</th></tr>
    </thead>
    <tbody>
      <tr><td>Principal</td><td class="num">{{ $payment['principal_allocated'] }}</td></tr>
      <tr class="alt"><td>Interest</td><td class="num">{{ $payment['interest_allocated'] }}</td></tr>
      <tr><td>Penalties &amp; charges</td><td class="num">{{ $payment['penalty_allocated'] }}</td></tr>
    </tbody>
    <tfoot>
      <tr><td>Total received</td><td class="num">{{ $cur }} {{ $payment['amount'] }}</td></tr>
    </tfoot>
  </table>

  <table class="cols" style="margin-top: 6px;">
    <tr>
      <td>
        <div class="section">Payment details</div>
        <table class="kv">
          <tr><td class="k">Method</td><td class="v">{{ $payment['payment_method'] }}</td></tr>
          @if(!empty($payment['reference']))<tr><td class="k">Reference</td><td class="v">{{ $payment['reference'] }}</td></tr>@endif
          @if(!empty($payment['recorded_by']))<tr><td class="k">Received by</td><td class="v">{{ $payment['recorded_by'] }}</td></tr>@endif
          @if(!empty($payment['notes']))<tr><td class="k">Notes</td><td class="v">{{ $payment['notes'] }}</td></tr>@endif
        </table>
      </td>
      <td class="gap"></td>
      <td>
        <table class="totals" style="width: 100%; margin-top: 30px;">
          <tr><td class="muted">Amount received</td><td class="v">{{ $cur }} {{ $payment['amount'] }}</td></tr>
          @isset($payment['loan_balance'])
            <tr class="grand"><td>Loan balance</td><td class="v" style="color: inherit;">{{ $cur }} {{ $payment['loan_balance'] }}</td></tr>
          @endisset
        </table>
      </td>
    </tr>
  </table>

  <div class="note">
    Thank you. This receipt confirms the payment above was received and applied to loan {{ $payment['loan_number'] }}.
    @isset($payment['loan_balance'])The loan balance shown is as at {{ $generatedAt ?? now()->format('d M Y H:i') }}.@endisset
  </div>
@endsection
