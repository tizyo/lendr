<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Loan Agreement — {{ $loan['loan_number'] }}</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; background: #fff; line-height: 1.5; }

  /* Header */
  .header { background: linear-gradient(135deg, #059669 0%, #047857 100%); color: #fff; padding: 28px 40px; display: flex; justify-content: space-between; align-items: flex-start; }
  .header-left h1 { font-size: 22px; font-weight: 700; letter-spacing: -0.3px; }
  .header-left p { font-size: 11px; opacity: 0.85; margin-top: 3px; }
  .header-right { text-align: right; }
  .header-right .doc-type { font-size: 14px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; opacity: 0.95; }
  .header-right .ref { font-size: 10px; opacity: 0.8; margin-top: 4px; }

  /* Divider stripe */
  .stripe { height: 4px; background: linear-gradient(90deg, #10b981, #34d399, #6ee7b7); }

  /* Content area */
  .content { padding: 28px 40px; }

  /* Section title */
  .section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: #059669; border-bottom: 1.5px solid #d1fae5; padding-bottom: 5px; margin-bottom: 12px; margin-top: 20px; }

  /* Info grid */
  .info-grid { display: table; width: 100%; }
  .info-row { display: table-row; }
  .info-label { display: table-cell; width: 38%; color: #6b7280; font-size: 10px; padding: 3px 0; vertical-align: top; }
  .info-value { display: table-cell; font-weight: 600; font-size: 10.5px; color: #111827; padding: 3px 0; }

  /* Two column layout */
  .two-col { width: 100%; display: table; border-spacing: 16px 0; margin: 0 -8px; }
  .col { display: table-cell; width: 50%; vertical-align: top; padding: 0 8px; }

  /* Amount highlight box */
  .amount-box { background: #ecfdf5; border: 1.5px solid #6ee7b7; border-radius: 6px; padding: 12px 16px; margin: 12px 0; }
  .amount-box .label { font-size: 10px; color: #059669; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
  .amount-box .value { font-size: 18px; font-weight: 700; color: #065f46; margin-top: 2px; }

  /* Terms table */
  .terms-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
  .terms-table th { background: #f0fdf4; color: #065f46; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 8px 10px; text-align: left; border: 1px solid #d1fae5; }
  .terms-table td { padding: 7px 10px; border: 1px solid #e5e7eb; font-size: 10.5px; color: #374151; }
  .terms-table tr:nth-child(even) td { background: #f9fafb; }

  /* Signatures */
  .sig-section { margin-top: 32px; display: table; width: 100%; }
  .sig-col { display: table-cell; width: 45%; vertical-align: bottom; padding-right: 40px; }
  .sig-line { border-top: 1.5px solid #374151; margin-top: 36px; padding-top: 6px; }
  .sig-name { font-weight: 700; font-size: 10.5px; }
  .sig-role { color: #6b7280; font-size: 10px; }

  /* Notice */
  .notice { background: #fffbeb; border: 1px solid #fde68a; border-radius: 5px; padding: 10px 14px; margin-top: 20px; font-size: 9.5px; color: #92400e; line-height: 1.6; }

  /* Footer */
  .footer { margin-top: 24px; border-top: 1px solid #e5e7eb; padding-top: 10px; text-align: center; font-size: 9px; color: #9ca3af; }
</style>
</head>
<body>

<div class="header">
  <div class="header-left">
    @if(!empty($logo_url))
      <img src="{{ $logo_url }}" alt="{{ $company }}" style="height:48px; max-width:160px; object-fit:contain; margin-bottom:6px; display:block;">
    @else
      <h1>{{ $company }}</h1>
    @endif
    @if(!empty($address))<p>{{ $address }}</p>@endif
    @if(!empty($phone))<p>{{ $phone }}</p>@endif
    @if(!empty($email))<p>{{ $email }}</p>@endif
  </div>
  <div class="header-right">
    <div class="doc-type">Loan Agreement</div>
    <div class="ref">Ref: {{ $loan['loan_number'] }}</div>
    <div class="ref">Date: {{ $generatedAt }}</div>
  </div>
</div>
<div class="stripe"></div>

<div class="content">

  <p style="font-size:12px; font-weight:700; color:#065f46; margin-bottom:4px;">LOAN AGREEMENT &amp; DISCLOSURE</p>
  <p style="font-size:10px; color:#6b7280;">This agreement is entered into between <strong>{{ $company }}</strong> ("Lender") and the borrower identified below ("Borrower").</p>

  <div class="section-title">Borrower Information</div>
  <div class="two-col">
    <div class="col">
      <div class="info-grid">
        <div class="info-row"><span class="info-label">Full Name</span><span class="info-value">{{ $loan['borrower']['name'] }}</span></div>
        <div class="info-row"><span class="info-label">Borrower No.</span><span class="info-value">{{ $loan['borrower']['borrower_number'] }}</span></div>
        <div class="info-row"><span class="info-label">Phone</span><span class="info-value">{{ $loan['borrower']['phone'] }}</span></div>
      </div>
    </div>
    <div class="col">
      <div class="info-grid">
        <div class="info-row"><span class="info-label">City</span><span class="info-value">{{ $loan['borrower']['city'] ?? '—' }}</span></div>
        <div class="info-row"><span class="info-label">KYC Status</span><span class="info-value">Verified</span></div>
      </div>
    </div>
  </div>

  <div class="section-title">Loan Details</div>
  <div class="two-col">
    <div class="col">
      <div class="amount-box">
        <div class="label">Principal Amount</div>
        <div class="value">{{ $loan['currency'] ?? 'ZMW' }} {{ $loan['principal_amount'] }}</div>
      </div>
      <div class="info-grid">
        <div class="info-row"><span class="info-label">Loan Number</span><span class="info-value">{{ $loan['loan_number'] }}</span></div>
        <div class="info-row"><span class="info-label">Loan Type</span><span class="info-value">{{ $loan['loan_type'] }}</span></div>
        <div class="info-row"><span class="info-label">Loan Plan</span><span class="info-value">{{ $loan['loan_plan'] }}</span></div>
        <div class="info-row"><span class="info-label">Application Date</span><span class="info-value">{{ $loan['application_date'] }}</span></div>
        @if($loan['disbursement_date'])
        <div class="info-row"><span class="info-label">Disbursement Date</span><span class="info-value">{{ $loan['disbursement_date'] }}</span></div>
        @endif
      </div>
    </div>
    <div class="col">
      <div class="amount-box">
        <div class="label">Total Repayable</div>
        <div class="value">{{ $loan['currency'] ?? 'ZMW' }} {{ $loan['total_payable'] }}</div>
      </div>
      <div class="info-grid">
        <div class="info-row"><span class="info-label">Interest Rate</span><span class="info-value">{{ $loan['interest_rate'] }}% {{ $loan['interest_type'] }}</span></div>
        <div class="info-row"><span class="info-label">Interest Amount</span><span class="info-value">{{ $loan['currency'] ?? 'ZMW' }} {{ $loan['interest_amount'] }}</span></div>
        <div class="info-row"><span class="info-label">Processing Fee</span><span class="info-value">{{ $loan['currency'] ?? 'ZMW' }} {{ $loan['processing_fee'] }}</span></div>
        <div class="info-row"><span class="info-label">Insurance Fee</span><span class="info-value">{{ $loan['currency'] ?? 'ZMW' }} {{ $loan['insurance_fee'] }}</span></div>
        <div class="info-row"><span class="info-label">Tenure</span><span class="info-value">{{ $loan['tenure'] }} {{ $loan['tenure_type'] }}</span></div>
        @if($loan['maturity_date'])
        <div class="info-row"><span class="info-label">Maturity Date</span><span class="info-value">{{ $loan['maturity_date'] }}</span></div>
        @endif
      </div>
    </div>
  </div>

  @if($loan['loan_purpose'] || $loan['collateral_description'])
  <div class="section-title">Purpose &amp; Collateral</div>
  <div class="two-col">
    <div class="col">
      @if($loan['loan_purpose'])
      <div class="info-grid">
        <div class="info-row"><span class="info-label">Loan Purpose</span><span class="info-value">{{ $loan['loan_purpose'] }}</span></div>
      </div>
      @endif
    </div>
    <div class="col">
      @if($loan['collateral_description'])
      <div class="info-grid">
        <div class="info-row"><span class="info-label">Collateral</span><span class="info-value">{{ $loan['collateral_description'] }}</span></div>
      </div>
      @endif
    </div>
  </div>
  @endif

  @if($loan['guarantor_name'])
  <div class="section-title">Guarantor</div>
  <div class="info-grid">
    <div class="info-row"><span class="info-label">Guarantor Name</span><span class="info-value">{{ $loan['guarantor_name'] }}</span></div>
    <div class="info-row"><span class="info-label">Phone</span><span class="info-value">{{ $loan['guarantor_phone'] ?? '—' }}</span></div>
    <div class="info-row"><span class="info-label">Relationship</span><span class="info-value">{{ $loan['guarantor_relationship'] ?? '—' }}</span></div>
  </div>
  @endif

  <div class="section-title">Repayment Schedule (Summary)</div>
  <table class="terms-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Due Date</th>
        <th>Total Due ({{ $loan['currency'] ?? 'ZMW' }})</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      @foreach($loan['schedule'] as $row)
      <tr>
        <td>{{ $row['instalment_number'] }}</td>
        <td>{{ $row['due_date'] }}</td>
        <td>{{ $row['total_due'] }}</td>
        <td>{{ $row['is_paid'] ? 'Paid' : ($row['days_overdue'] > 0 ? 'Overdue' : 'Pending') }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>

  <div class="section-title">Terms &amp; Conditions</div>
  <p style="font-size:10px; color:#374151; line-height:1.7;">
    1. The Borrower agrees to repay the loan in accordance with the repayment schedule set out above.<br>
    2. Overdue instalments attract a daily penalty charge at the agreed penalty rate.<br>
    3. Early repayment is permitted without penalty unless otherwise specified in the loan plan.<br>
    4. The Lender reserves the right to recover outstanding amounts through available legal channels in the event of default.<br>
    5. This agreement is governed by the laws of the jurisdiction in which {{ $company }} operates.<br>
    6. By signing this agreement, the Borrower confirms that all information provided is accurate and complete.
  </p>

  <div class="sig-section">
    <div class="sig-col">
      <div class="sig-line">
        <div class="sig-name">{{ $loan['borrower']['name'] }}</div>
        <div class="sig-role">Borrower Signature &amp; Date</div>
      </div>
    </div>
    <div class="sig-col">
      <div class="sig-line">
        <div class="sig-name">{{ $loan['approved_by'] ?? 'Authorised Signatory' }}</div>
        <div class="sig-role">Lender Representative &amp; Date</div>
      </div>
    </div>
  </div>

  <div class="notice">
    <strong>Notice:</strong> This is a legally binding agreement. Please read carefully before signing. If you have any questions regarding the terms, contact {{ $company }} before proceeding.
  </div>

  <div class="footer">
    @if(!empty($invoice_footer))
      {{ $invoice_footer }}<br>
    @endif
    &copy; {{ date('Y') }} {{ $company }}. This document was generated on {{ $generatedAt }}. | Loan Agreement — {{ $loan['loan_number'] }}
  </div>

</div>
</body>
</html>
