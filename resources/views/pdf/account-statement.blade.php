@extends('pdf.layout')

@php($cur = $currency ?? 'ZMW')
@php($money = fn ($v) => number_format((float) $v, 2))

@section('title', 'Account Statement')
@section('ref', 'Statement '.$borrower['borrower_number'])

@section('meta')
  Borrower No. <strong>{{ $borrower['borrower_number'] }}</strong><br>
  Statement date <strong>{{ now()->format('d M Y') }}</strong><br>
  @if($borrower['kyc_verified'] ?? false)<span class="pill pill-green">KYC VERIFIED</span>@else<span class="pill pill-amber">KYC PENDING</span>@endif
@endsection

@section('content')
  <table class="cols" style="margin-top: 14px;">
    <tr>
      <td>
        <div class="section" style="margin-top: 0;">Account holder</div>
        <div class="bold" style="font-size: 11px; color: #111827;">{{ $borrower['name'] }}</div>
        <div class="muted">{{ collect([$borrower['phone'] ?? null, $borrower['email'] ?? null])->filter()->implode('  ·  ') }}</div>
        @if(!empty($borrower['city']))<div class="muted">{{ $borrower['city'] }}</div>@endif
      </td>
      <td class="gap"></td>
      <td>
        <div class="section" style="margin-top: 0;">Summary</div>
        <table class="kv">
          <tr><td class="k">Loans</td><td class="v">{{ count($loans) }}</td></tr>
          @if(!empty($borrower['credit_score']))<tr><td class="k">Credit score</td><td class="v">{{ $borrower['credit_score'] }}</td></tr>@endif
        </table>
      </td>
    </tr>
  </table>

  <table class="stats">
    <tr>
      <td><div class="stat-label">Total borrowed</div><div class="stat-value">{{ $cur }} {{ $money($summary['total_disbursed']) }}</div></td>
      <td><div class="stat-label">Total repaid</div><div class="stat-value">{{ $cur }} {{ $money($summary['total_paid']) }}</div></td>
      <td><div class="stat-label">Outstanding</div><div class="stat-value brand">{{ $cur }} {{ $money($summary['outstanding']) }}</div></td>
    </tr>
  </table>

  @forelse($loans as $loan)
    @php($tone = match ($loan['status']) { 'completed' => 'green', 'active', 'disbursed' => 'brand', 'defaulted', 'written_off' => 'red', default => 'grey' })
    <div class="section">
      Loan {{ $loan['loan_number'] }} · {{ $loan['loan_type'] }}
      <span class="pill pill-{{ $tone }}" style="letter-spacing: 0; margin-left: 6px;">{{ strtoupper($loan['status_label']) }}</span>
    </div>
    <table class="kv kv-wide">
      <tr>
        <td class="k">Principal</td><td class="v">{{ $cur }} {{ $loan['principal_amount'] }}</td>
        <td class="k">Repayable</td><td class="v">{{ $cur }} {{ $loan['total_payable'] }}</td>
        <td class="k">Balance</td><td class="v">{{ $cur }} {{ $loan['outstanding_balance'] }}</td>
      </tr>
      <tr>
        <td class="k">Disbursed</td><td class="v">{{ $loan['disbursement_date'] ?? '—' }}</td>
        <td class="k">Matures</td><td class="v">{{ $loan['maturity_date'] ?? '—' }}</td>
        <td class="k">Paid</td><td class="v">{{ $cur }} {{ $loan['total_paid'] }}</td>
      </tr>
    </table>

    @if(!empty($loan['payments']))
      <table class="grid" style="margin-top: 6px;">
        <thead>
          <tr><th>Date</th><th>Receipt</th><th>Method</th><th class="num">Principal</th><th class="num">Interest</th><th class="num">Amount ({{ $cur }})</th></tr>
        </thead>
        <tbody>
          @foreach($loan['payments'] as $p)
            <tr class="{{ $loop->even ? 'alt' : '' }}">
              <td>{{ $p['payment_date'] }}</td>
              <td>{{ $p['receipt_number'] }}</td>
              <td>{{ $p['payment_method'] }}</td>
              <td class="num">{{ $p['principal_allocated'] }}</td>
              <td class="num">{{ $p['interest_allocated'] }}</td>
              <td class="num bold">{{ $p['amount'] }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @else
      <p class="muted small" style="margin-top: 4px;">No payments recorded on this loan.</p>
    @endif
  @empty
    <p class="muted" style="margin-top: 14px;">No loans on this account.</p>
  @endforelse

  <div class="note">Balances are as at {{ $generatedAt ?? now()->format('d M Y H:i') }}. Please report any discrepancy within 14 days.</div>
@endsection
