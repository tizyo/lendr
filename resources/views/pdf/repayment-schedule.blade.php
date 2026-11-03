@extends('pdf.layout')

@php($cur = $loan['currency'] ?? ($currency ?? 'ZMW'))

@section('title', 'Repayment Schedule')
@section('ref', 'Repayment Schedule '.$loan['loan_number'])

@section('meta')
  Loan No. <strong>{{ $loan['loan_number'] }}</strong><br>
  Borrower <strong>{{ $loan['borrower']['name'] }}</strong> ({{ $loan['borrower']['borrower_number'] }})<br>
  <span class="pill pill-brand">{{ strtoupper($loan['status_label'] ?? '') }}</span>
@endsection

@section('content')
  <table class="stats" style="margin-top: 14px;">
    <tr>
      <td><div class="stat-label">Principal</div><div class="stat-value">{{ $cur }} {{ $loan['principal_amount'] }}</div></td>
      <td><div class="stat-label">Total repayable</div><div class="stat-value">{{ $cur }} {{ $loan['total_payable'] }}</div></td>
      <td><div class="stat-label">Paid to date</div><div class="stat-value">{{ $cur }} {{ $loan['total_paid'] }}</div></td>
      <td><div class="stat-label">Outstanding</div><div class="stat-value brand">{{ $cur }} {{ $loan['outstanding_balance'] }}</div></td>
    </tr>
  </table>

  <table class="kv kv-wide" style="margin-top: 10px;">
    <tr>
      <td class="k">Product</td><td class="v">{{ $loan['loan_type'] }} · {{ $loan['loan_plan'] }}</td>
      <td class="k">Interest</td><td class="v">{{ rtrim(rtrim((string) $loan['interest_rate'], '0'), '.') }}% {{ str_replace('_', ' ', $loan['interest_type']) }}</td>
    </tr>
    <tr>
      <td class="k">Tenure</td><td class="v">{{ $loan['tenure'] }} {{ $loan['tenure_type'] }} ({{ str_replace('_', '-', $loan['repayment_schedule'] ?? '') }})</td>
      <td class="k">Period</td><td class="v">{{ $loan['first_repayment_date'] ?? '—' }} to {{ $loan['maturity_date'] ?? '—' }}</td>
    </tr>
    @if((float) str_replace(',', '', $loan['penalty_balance'] ?? '0') > 0)
      <tr><td class="k">Penalties due</td><td class="v" style="color: #b91c1c;">{{ $cur }} {{ $loan['penalty_balance'] }}</td><td></td><td></td></tr>
    @endif
  </table>

  <div class="section">Instalments</div>
  @if(empty($loan['schedule']))
    <p class="muted">The schedule is created when the loan is disbursed.</p>
  @else
    <table class="grid">
      <thead>
        <tr>
          <th style="width: 6%;">#</th><th>Due date</th><th class="num">Principal</th><th class="num">Interest</th>
          <th class="num">Total due</th><th class="num">Paid</th><th class="num">Balance</th><th style="width: 11%;">Status</th>
        </tr>
      </thead>
      <tbody>
        @foreach($loan['schedule'] as $row)
          @php($state = $row['is_paid'] ? ['Paid', 'green'] : (($row['days_overdue'] ?? 0) > 0 ? ['Overdue', 'red'] : ['Upcoming', 'grey']))
          <tr class="{{ $loop->even ? 'alt' : '' }}">
            <td>{{ $row['instalment_number'] }}</td>
            <td>{{ $row['due_date'] }}</td>
            <td class="num">{{ $row['principal_due'] ?? '—' }}</td>
            <td class="num">{{ $row['interest_due'] ?? '—' }}</td>
            <td class="num bold">{{ $row['total_due'] }}</td>
            <td class="num">{{ $row['total_paid'] }}</td>
            <td class="num">{{ $row['outstanding'] }}</td>
            <td><span class="pill pill-{{ $state[1] }}">{{ $state[0] }}</span></td>
          </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr>
          <td colspan="4">Totals ({{ $cur }})</td>
          <td class="num">{{ $loan['total_payable'] }}</td>
          <td class="num">{{ $loan['total_paid'] }}</td>
          <td class="num">{{ $loan['outstanding_balance'] }}</td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  @endif

  <div class="note">Pay on or before each due date to avoid penalties. Quote loan number {{ $loan['loan_number'] }} as the reference on every payment.</div>
@endsection
