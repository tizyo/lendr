@extends('pdf.layout')

@php($cur = $loan['currency'] ?? 'ZMW')
@php($lender = ($brand ??= \App\Support\DocumentBrand::current())['name'])

@section('title', 'Disbursement Letter')
@section('ref', 'Disbursement Letter '.$loan['loan_number'])

@section('meta')
  Loan No. <strong>{{ $loan['loan_number'] }}</strong><br>
  Date <strong>{{ $loan['disbursement_date'] ?? now()->format('d M Y') }}</strong><br>
  <span class="pill pill-green">DISBURSED</span>
@endsection

@section('content')
  <table style="width: 100%; margin-top: 16px;">
    <tr>
      <td style="vertical-align: top;">
        <div class="muted small" style="text-transform: uppercase; letter-spacing: 0.6px;">To</div>
        <div class="bold" style="font-size: 11px; color: #111827;">{{ $loan['borrower']['name'] }}</div>
        <div class="muted">Borrower No. {{ $loan['borrower']['borrower_number'] }}</div>
        @if(!empty($loan['borrower']['city']))<div class="muted">{{ $loan['borrower']['city'] }}</div>@endif
        <div class="muted">{{ collect([$loan['borrower']['phone'] ?? null, $loan['borrower']['email'] ?? null])->filter()->implode('  ·  ') }}</div>
      </td>
    </tr>
  </table>

  <p class="bold" style="margin-top: 16px; font-size: 11px; color: #111827;">Re: Disbursement of loan {{ $loan['loan_number'] }}</p>
  <p style="margin-top: 8px; color: #374151;">
    Dear {{ $loan['borrower']['name'] }},
  </p>
  <p style="margin-top: 6px; color: #374151;">
    We are pleased to confirm that your {{ strtolower($loan['loan_type']) }} of <strong>{{ $cur }} {{ $loan['principal_amount'] }}</strong>
    has been disbursed{{ !empty($loan['disbursement_method']) ? ' by '.$loan['disbursement_method'] : '' }} on {{ $loan['disbursement_date'] ?? 'the date above' }}.
    The details are below.
  </p>

  <table class="stats">
    <tr>
      <td><div class="stat-label">Amount disbursed</div><div class="stat-value brand">{{ $cur }} {{ $loan['principal_amount'] }}</div></td>
      <td><div class="stat-label">First repayment</div><div class="stat-value">{{ $loan['first_repayment_date'] ?? '—' }}</div></td>
      <td><div class="stat-label">Final repayment</div><div class="stat-value">{{ $loan['maturity_date'] ?? '—' }}</div></td>
    </tr>
  </table>

  <div class="section">Disbursement details</div>
  <table class="cols">
    <tr>
      <td>
        <table class="kv">
          <tr><td class="k">Method</td><td class="v">{{ $loan['disbursement_method'] ?? '—' }}</td></tr>
          <tr><td class="k">Account / wallet</td><td class="v">{{ $loan['disbursement_account'] ?? '—' }}</td></tr>
          <tr><td class="k">Disbursed by</td><td class="v">{{ $loan['disbursed_by'] ?? '—' }}</td></tr>
        </table>
      </td>
      <td class="gap"></td>
      <td>
        <table class="kv">
          <tr><td class="k">Interest rate</td><td class="v">{{ rtrim(rtrim((string) $loan['interest_rate'], '0'), '.') }}% {{ str_replace('_', ' ', $loan['interest_type']) }}</td></tr>
          <tr><td class="k">Tenure</td><td class="v">{{ $loan['tenure'] }} {{ $loan['tenure_type'] }}</td></tr>
          @if(!empty($loan['loan_purpose']))<tr><td class="k">Purpose</td><td class="v">{{ $loan['loan_purpose'] }}</td></tr>@endif
        </table>
      </td>
    </tr>
  </table>

  <p style="margin-top: 14px; color: #374151;">
    Please repay according to the repayment schedule in your loan agreement, quoting loan number <strong>{{ $loan['loan_number'] }}</strong>
    as the reference. If anything above is incorrect, contact {{ $lender }} straight away.
  </p>

  <p style="margin-top: 14px; color: #374151;">Yours sincerely,</p>

  <table class="sig" style="margin-top: 26px;">
    <tr>
      <td>
        <div class="sig-line">
          <div class="sig-name">{{ $loan['disbursed_by'] ?? 'Authorised signatory' }}</div>
          <div class="muted small">For {{ $lender }}</div>
        </div>
      </td>
      <td class="gap"></td>
      <td></td>
    </tr>
  </table>
@endsection
