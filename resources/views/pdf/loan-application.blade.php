@extends('pdf.layout')

@php($cur = $loan['currency'] ?? 'ZMW')
@php($lender = ($brand ??= \App\Support\DocumentBrand::current())['name'])
@php($b = $loan['borrower'])
@php($corporate = ($b['borrower_type'] ?? 'individual') === 'corporate')

@section('title', 'Loan Application')
@section('ref', 'Loan Application '.$loan['loan_number'])

@section('meta')
  Application No. <strong>{{ $loan['loan_number'] }}</strong><br>
  Date <strong>{{ $loan['application_date'] ?? '—' }}</strong><br>
  <span class="pill pill-brand">{{ $corporate ? 'CORPORATE' : 'INDIVIDUAL' }}</span>
@endsection

@section('content')
  <p class="muted small" style="margin-top: 12px;">
    Records the request submitted for assessment. This is not a loan offer; approved applicants receive a separate Loan Agreement.
  </p>

  <table class="stats">
    <tr>
      <td><div class="stat-label">Amount requested</div><div class="stat-value brand">{{ $cur }} {{ $loan['principal_amount'] }}</div></td>
      <td><div class="stat-label">Product</div><div class="stat-value" style="font-size: 10px;">{{ $loan['loan_type'] }}<br><span class="muted" style="font-weight: normal;">{{ $loan['loan_plan'] }}</span></div></td>
      <td><div class="stat-label">Tenure</div><div class="stat-value">{{ $loan['tenure'] }} {{ $loan['tenure_type'] }}</div></td>
      <td><div class="stat-label">Repayments</div><div class="stat-value">{{ ucfirst(str_replace('_', '-', $loan['repayment_schedule'] ?? '—')) }}</div></td>
    </tr>
  </table>

  @if($corporate)
    <table class="cols">
      <tr>
        <td>
          <div class="section">Company</div>
          <table class="kv">
            <tr><td class="k">Registered name</td><td class="v">{{ $b['name'] }}</td></tr>
            @if(!empty($b['trading_name']))<tr><td class="k">Trading as</td><td class="v">{{ $b['trading_name'] }}</td></tr>@endif
            <tr><td class="k">Borrower No.</td><td class="v">{{ $b['borrower_number'] }}</td></tr>
            <tr><td class="k">Business type</td><td class="v">{{ $b['business_type'] ?? '—' }}</td></tr>
            <tr><td class="k">Industry</td><td class="v">{{ $b['industry'] ?? '—' }}</td></tr>
            <tr><td class="k">PACRA No.</td><td class="v">{{ $b['company_reg_number'] ?? '—' }}</td></tr>
            <tr><td class="k">TPIN</td><td class="v">{{ $b['tpin_number'] ?? '—' }}</td></tr>
            <tr><td class="k">Incorporated</td><td class="v">{{ $b['incorporation_date'] ?? '—' }}</td></tr>
          </table>
        </td>
        <td class="gap"></td>
        <td>
          <div class="section">Contact</div>
          <table class="kv">
            <tr><td class="k">Phone</td><td class="v">{{ $b['phone'] }}</td></tr>
            <tr><td class="k">Email</td><td class="v">{{ $b['email'] ?? '—' }}</td></tr>
            <tr><td class="k">Address</td><td class="v">{{ collect([$b['address'] ?? null, $b['city'] ?? null, $b['province'] ?? null])->filter()->implode(', ') ?: '—' }}</td></tr>
            <tr><td class="k">Contact person</td><td class="v">{{ $b['contact_person_name'] ?? '—' }}@if(!empty($b['contact_person_position'])), {{ $b['contact_person_position'] }}@endif</td></tr>
            <tr><td class="k">Their phone</td><td class="v">{{ $b['contact_person_phone'] ?? '—' }}</td></tr>
          </table>
        </td>
      </tr>
    </table>
  @else
    <table class="cols">
      <tr>
        <td>
          <div class="section">Applicant</div>
          <table class="kv">
            <tr><td class="k">Full name</td><td class="v">{{ $b['name'] }}</td></tr>
            <tr><td class="k">Borrower No.</td><td class="v">{{ $b['borrower_number'] }}</td></tr>
            <tr><td class="k">NRC</td><td class="v">{{ $b['national_id'] ?? '—' }}</td></tr>
            <tr><td class="k">Date of birth</td><td class="v">{{ $b['date_of_birth'] ?? '—' }}</td></tr>
            <tr><td class="k">Gender</td><td class="v">{{ ucfirst($b['gender'] ?? '—') }}</td></tr>
            <tr><td class="k">Occupation</td><td class="v">{{ $b['occupation'] ?? '—' }}</td></tr>
            <tr><td class="k">Employer</td><td class="v">{{ $b['employer'] ?? '—' }}</td></tr>
          </table>
        </td>
        <td class="gap"></td>
        <td>
          <div class="section">Contact &amp; next of kin</div>
          <table class="kv">
            <tr><td class="k">Phone</td><td class="v">{{ $b['phone'] }}</td></tr>
            <tr><td class="k">Email</td><td class="v">{{ $b['email'] ?? '—' }}</td></tr>
            <tr><td class="k">Address</td><td class="v">{{ collect([$b['address'] ?? null, $b['city'] ?? null, $b['province'] ?? null])->filter()->implode(', ') ?: '—' }}</td></tr>
            <tr><td class="k">Next of kin</td><td class="v">{{ $b['next_of_kin_name'] ?? '—' }}@if(!empty($b['next_of_kin_relationship'])) ({{ $b['next_of_kin_relationship'] }})@endif</td></tr>
            <tr><td class="k">Their phone</td><td class="v">{{ $b['next_of_kin_phone'] ?? '—' }}</td></tr>
          </table>
        </td>
      </tr>
    </table>
  @endif

  <table class="cols">
    <tr>
      <td>
        <div class="section">Purpose &amp; collateral</div>
        <table class="kv">
          <tr><td class="k">Purpose</td><td class="v">{{ $loan['loan_purpose'] ?: '—' }}</td></tr>
          <tr><td class="k">Collateral</td><td class="v">{{ $loan['collateral_description'] ?: 'None offered' }}</td></tr>
        </table>
      </td>
      <td class="gap"></td>
      <td>
        <div class="section">Guarantor</div>
        <table class="kv">
          <tr><td class="k">Name</td><td class="v">{{ $loan['guarantor_name'] ?: '—' }}</td></tr>
          <tr><td class="k">Phone</td><td class="v">{{ $loan['guarantor_phone'] ?: '—' }}</td></tr>
          <tr><td class="k">Relationship</td><td class="v">{{ $loan['guarantor_relationship'] ?: '—' }}</td></tr>
        </table>
      </td>
    </tr>
  </table>

  <div class="section">Documents sighted at intake</div>
  <table class="grid">
    <thead><tr><th style="width: 9%;">✓</th><th>Document</th></tr></thead>
    <tbody>
      @foreach($corporate
          ? ['Certificate of Incorporation (PACRA)', 'TPIN certificate', 'Board resolution authorising the loan', 'Directors’ NRCs / passports', 'Bank statements (last 6 months)']
          : ['NRC / passport (original and copy)', 'Proof of address (utility bill, lease or similar)', 'Proof of income (payslip, bank statement or business records)', 'Passport-size photograph', 'Guarantor NRC (if applicable)'] as $doc)
        <tr class="{{ $loop->even ? 'alt' : '' }}"><td>☐</td><td>{{ $doc }}</td></tr>
      @endforeach
    </tbody>
  </table>

  <div class="section">Declaration &amp; consent</div>
  <p class="terms">
    I declare that the information in this application is true and complete. I consent to {{ $lender }} verifying it, including with credit
    reference bureaus, to assess this application and manage any resulting loan. I understand that submitting this form does not guarantee
    approval and that final terms are set in the loan agreement issued on approval.
  </p>

  <table class="sig">
    <tr>
      <td>
        <div class="sig-line">
          <div class="sig-name">{{ $corporate ? ($b['contact_person_name'] ?? $b['name']) : $b['name'] }}</div>
          <div class="muted small">{{ $corporate ? 'For '.$b['name'].' · signature & date' : 'Applicant · signature & date' }}</div>
        </div>
      </td>
      <td class="gap"></td>
      <td>
        <div class="sig-line">
          <div class="sig-name">{{ $loan['created_by'] ?? 'Loan officer' }}</div>
          <div class="muted small">Received by · signature &amp; date</div>
        </div>
      </td>
    </tr>
  </table>
@endsection
