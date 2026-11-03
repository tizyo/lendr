@extends('pdf.layout')

@php($cur = $loan['currency'] ?? 'ZMW')
@php($lender = ($brand ??= \App\Support\DocumentBrand::current())['name'])

@section('title', 'Loan Agreement')
@section('ref', 'Loan Agreement '.$loan['loan_number'])

@section('meta')
  Loan No. <strong>{{ $loan['loan_number'] }}</strong><br>
  Application date <strong>{{ $loan['application_date'] ?? '—' }}</strong><br>
  @if(!empty($loan['disbursement_date']))Disbursed <strong>{{ $loan['disbursement_date'] }}</strong>@endif
@endsection

@section('content')
  <p style="margin-top: 14px; color: #374151;">
    This agreement is made between <strong>{{ $lender }}</strong> (the "Lender") and
    <strong>{{ $loan['borrower']['name'] }}</strong> (the "Borrower") on the terms below.
  </p>

  <table class="stats">
    <tr>
      <td><div class="stat-label">Principal</div><div class="stat-value">{{ $cur }} {{ $loan['principal_amount'] }}</div></td>
      <td><div class="stat-label">Interest</div><div class="stat-value">{{ $cur }} {{ $loan['interest_amount'] }}</div></td>
      <td><div class="stat-label">Fees</div><div class="stat-value">{{ $cur }} {{ number_format((float) str_replace(',', '', $loan['processing_fee']) + (float) str_replace(',', '', $loan['insurance_fee']), 2) }}</div></td>
      <td><div class="stat-label">Total repayable</div><div class="stat-value brand">{{ $cur }} {{ $loan['total_payable'] }}</div></td>
    </tr>
  </table>

  <table class="cols">
    <tr>
      <td>
        <div class="section">Borrower</div>
        <table class="kv">
          <tr><td class="k">Name</td><td class="v">{{ $loan['borrower']['name'] }}</td></tr>
          <tr><td class="k">Borrower No.</td><td class="v">{{ $loan['borrower']['borrower_number'] }}</td></tr>
          <tr><td class="k">Phone</td><td class="v">{{ $loan['borrower']['phone'] ?? '—' }}</td></tr>
          @if(!empty($loan['borrower']['city']))<tr><td class="k">City</td><td class="v">{{ $loan['borrower']['city'] }}</td></tr>@endif
          @isset($loan['borrower']['kyc_verified'])
            <tr><td class="k">KYC</td><td class="v">{{ $loan['borrower']['kyc_verified'] ? 'Verified' : 'Not yet verified' }}</td></tr>
          @endisset
        </table>
      </td>
      <td class="gap"></td>
      <td>
        <div class="section">Loan terms</div>
        <table class="kv">
          <tr><td class="k">Product</td><td class="v">{{ $loan['loan_type'] }} · {{ $loan['loan_plan'] }}</td></tr>
          <tr><td class="k">Interest rate</td><td class="v">{{ rtrim(rtrim((string) $loan['interest_rate'], '0'), '.') }}% {{ str_replace('_', ' ', $loan['interest_type']) }}</td></tr>
          <tr><td class="k">Tenure</td><td class="v">{{ $loan['tenure'] }} {{ $loan['tenure_type'] }}, {{ str_replace('_', '-', $loan['repayment_schedule'] ?? '') }} repayments</td></tr>
          <tr><td class="k">Processing fee</td><td class="v">{{ $cur }} {{ $loan['processing_fee'] }}</td></tr>
          <tr><td class="k">Insurance fee</td><td class="v">{{ $cur }} {{ $loan['insurance_fee'] }}</td></tr>
          @if(!empty($loan['maturity_date']))<tr><td class="k">Maturity</td><td class="v">{{ $loan['maturity_date'] }}</td></tr>@endif
        </table>
      </td>
    </tr>
  </table>

  @if(!empty($loan['loan_purpose']) || !empty($loan['collateral_description']) || !empty($loan['guarantor_name']))
  <table class="cols">
    <tr>
      <td>
        <div class="section">Purpose &amp; collateral</div>
        <table class="kv">
          <tr><td class="k">Purpose</td><td class="v">{{ $loan['loan_purpose'] ?: '—' }}</td></tr>
          <tr><td class="k">Collateral</td><td class="v">{{ $loan['collateral_description'] ?: 'None' }}</td></tr>
        </table>
      </td>
      <td class="gap"></td>
      <td>
        <div class="section">Guarantor</div>
        @if(!empty($loan['guarantor_name']))
        <table class="kv">
          <tr><td class="k">Name</td><td class="v">{{ $loan['guarantor_name'] }}</td></tr>
          <tr><td class="k">Phone</td><td class="v">{{ $loan['guarantor_phone'] ?: '—' }}</td></tr>
          <tr><td class="k">Relationship</td><td class="v">{{ $loan['guarantor_relationship'] ?: '—' }}</td></tr>
        </table>
        @else
          <p class="muted">No guarantor.</p>
        @endif
      </td>
    </tr>
  </table>
  @endif

  @if(!empty($loan['schedule']))
    <div class="section">Repayment schedule @if(!empty($loan['schedule_projected']))<span class="muted" style="text-transform: none; letter-spacing: 0; font-weight: normal;">(projected; dates are confirmed at disbursement)</span>@endif</div>
    <table class="grid">
      <thead>
        <tr><th style="width: 7%;">#</th><th>Due date</th><th class="num">Principal</th><th class="num">Interest</th><th class="num">Total due ({{ $cur }})</th></tr>
      </thead>
      <tbody>
        @foreach($loan['schedule'] as $row)
          <tr class="{{ $loop->even ? 'alt' : '' }}">
            <td>{{ $row['instalment_number'] }}</td>
            <td>{{ $row['due_date'] }}</td>
            <td class="num">{{ $row['principal_due'] ?? '—' }}</td>
            <td class="num">{{ $row['interest_due'] ?? '—' }}</td>
            <td class="num bold">{{ $row['total_due'] }}</td>
          </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr><td colspan="4">Total repayable</td><td class="num">{{ $cur }} {{ $loan['total_payable'] }}</td></tr>
      </tfoot>
    </table>
  @endif

  <div class="section">Terms &amp; conditions</div>
  <ol class="terms" style="padding-left: 14px;">
    <li>The Borrower will repay the loan according to the repayment schedule above.</li>
    <li>Overdue instalments attract a penalty at the rate set in the loan plan.</li>
    <li>Early repayment is allowed without penalty unless the loan plan says otherwise.</li>
    <li>If the Borrower defaults, the Lender may recover the amount owed through lawful means, including any collateral and guarantee above.</li>
    <li>This agreement is governed by the laws of the Republic of Zambia.</li>
    <li>By signing, the Borrower confirms that the information they gave is true and complete.</li>
  </ol>

  <table class="sig">
    <tr>
      <td>
        <div class="sig-line">
          <div class="sig-name">{{ $loan['borrower']['name'] }}</div>
          <div class="muted small">Borrower · signature &amp; date</div>
        </div>
      </td>
      <td class="gap"></td>
      <td>
        <div class="sig-line">
          <div class="sig-name">{{ $loan['approved_by'] ?? 'Authorised signatory' }}</div>
          <div class="muted small">For {{ $lender }} · signature &amp; date</div>
        </div>
      </td>
    </tr>
  </table>
@endsection
