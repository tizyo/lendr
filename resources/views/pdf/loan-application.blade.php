<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Loan Application — {{ $loan['loan_number'] }}</title>
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

  /* Checklist */
  .checklist { width: 100%; border-collapse: collapse; margin-top: 8px; }
  .checklist th { background: #f0fdf4; color: #065f46; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 8px 10px; text-align: left; border: 1px solid #d1fae5; }
  .checklist td { padding: 8px 10px; border: 1px solid #e5e7eb; font-size: 10.5px; color: #374151; }
  .checklist .box { display: inline-block; width: 11px; height: 11px; border: 1.3px solid #6b7280; border-radius: 2px; }

  /* Signatures */
  .sig-section { margin-top: 32px; display: table; width: 100%; }
  .sig-col { display: table-cell; width: 45%; vertical-align: bottom; padding-right: 40px; }
  .sig-line { border-top: 1.5px solid #374151; margin-top: 36px; padding-top: 6px; }
  .sig-name { font-weight: 700; font-size: 10.5px; }
  .sig-role { color: #6b7280; font-size: 10px; }

  /* Declaration */
  .declaration { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 5px; padding: 12px 16px; margin-top: 8px; font-size: 9.5px; color: #374151; line-height: 1.7; }

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
    <div class="doc-type">Loan Application</div>
    <div class="ref">Ref: {{ $loan['loan_number'] }}</div>
    <div class="ref">Date: {{ $generatedAt }}</div>
  </div>
</div>
<div class="stripe"></div>

<div class="content">

  <p style="font-size:12px; font-weight:700; color:#065f46; margin-bottom:4px;">LOAN APPLICATION FORM</p>
  <p style="font-size:10px; color:#6b7280;">To be completed in full by the applicant and countersigned by the receiving loan officer. This form is not a loan offer or agreement — it records the request submitted for assessment.</p>

  <div class="section-title">Applicant Personal Details</div>
  <div class="two-col">
    <div class="col">
      <div class="info-grid">
        <div class="info-row"><span class="info-label">Full Name</span><span class="info-value">{{ $loan['borrower']['name'] }}</span></div>
        <div class="info-row"><span class="info-label">Borrower No.</span><span class="info-value">{{ $loan['borrower']['borrower_number'] }}</span></div>
        <div class="info-row"><span class="info-label">Date of Birth</span><span class="info-value">{{ $loan['borrower']['date_of_birth'] ?? '—' }}</span></div>
        <div class="info-row"><span class="info-label">Gender</span><span class="info-value">{{ $loan['borrower']['gender'] ?? '—' }}</span></div>
        <div class="info-row"><span class="info-label">National ID</span><span class="info-value">{{ $loan['borrower']['national_id'] ?? '—' }}</span></div>
      </div>
    </div>
    <div class="col">
      <div class="info-grid">
        <div class="info-row"><span class="info-label">Phone</span><span class="info-value">{{ $loan['borrower']['phone'] }}</span></div>
        <div class="info-row"><span class="info-label">Email</span><span class="info-value">{{ $loan['borrower']['email'] ?? '—' }}</span></div>
        <div class="info-row"><span class="info-label">Address</span><span class="info-value">{{ $loan['borrower']['address'] ?? '—' }}</span></div>
        <div class="info-row"><span class="info-label">City / Province</span><span class="info-value">{{ $loan['borrower']['city'] ?? '—' }}@if($loan['borrower']['province'] ?? null), {{ $loan['borrower']['province'] }}@endif</span></div>
      </div>
    </div>
  </div>

  <div class="section-title">Employment &amp; Income</div>
  <div class="two-col">
    <div class="col">
      <div class="info-grid">
        <div class="info-row"><span class="info-label">Occupation</span><span class="info-value">{{ $loan['borrower']['occupation'] ?? '—' }}</span></div>
      </div>
    </div>
    <div class="col">
      <div class="info-grid">
        <div class="info-row"><span class="info-label">Employer</span><span class="info-value">{{ $loan['borrower']['employer'] ?? '—' }}</span></div>
      </div>
    </div>
  </div>

  <div class="section-title">Next of Kin</div>
  <div class="info-grid">
    <div class="info-row"><span class="info-label">Full Name</span><span class="info-value">{{ $loan['borrower']['next_of_kin_name'] ?? '—' }}</span></div>
    <div class="info-row"><span class="info-label">Phone</span><span class="info-value">{{ $loan['borrower']['next_of_kin_phone'] ?? '—' }}</span></div>
    <div class="info-row"><span class="info-label">Relationship</span><span class="info-value">{{ $loan['borrower']['next_of_kin_relationship'] ?? '—' }}</span></div>
  </div>

  <div class="section-title">Loan Requested</div>
  <div class="two-col">
    <div class="col">
      <div class="amount-box">
        <div class="label">Amount Requested</div>
        <div class="value">{{ $loan['currency'] ?? 'ZMW' }} {{ $loan['principal_amount'] }}</div>
      </div>
      <div class="info-grid">
        <div class="info-row"><span class="info-label">Loan Type</span><span class="info-value">{{ $loan['loan_type'] }}</span></div>
        <div class="info-row"><span class="info-label">Loan Plan</span><span class="info-value">{{ $loan['loan_plan'] }}</span></div>
      </div>
    </div>
    <div class="col">
      <div class="info-grid">
        <div class="info-row"><span class="info-label">Requested Tenure</span><span class="info-value">{{ $loan['tenure'] }} {{ $loan['tenure_type'] }}</span></div>
        <div class="info-row"><span class="info-label">Repayment Frequency</span><span class="info-value">{{ ucfirst($loan['repayment_schedule'] ?? '—') }}</span></div>
        <div class="info-row"><span class="info-label">Application Date</span><span class="info-value">{{ $loan['application_date'] }}</span></div>
      </div>
    </div>
  </div>

  @if($loan['loan_purpose'])
  <div class="section-title">Purpose of Loan</div>
  <p style="font-size:10.5px; color:#111827;">{{ $loan['loan_purpose'] }}</p>
  @endif

  @if($loan['collateral_description'] || $loan['guarantor_name'])
  <div class="section-title">Collateral &amp; Guarantor</div>
  <div class="two-col">
    <div class="col">
      <div class="info-grid">
        <div class="info-row"><span class="info-label">Collateral</span><span class="info-value">{{ $loan['collateral_description'] ?? 'None offered' }}</span></div>
      </div>
    </div>
    <div class="col">
      @if($loan['guarantor_name'])
      <div class="info-grid">
        <div class="info-row"><span class="info-label">Guarantor Name</span><span class="info-value">{{ $loan['guarantor_name'] }}</span></div>
        <div class="info-row"><span class="info-label">Guarantor Phone</span><span class="info-value">{{ $loan['guarantor_phone'] ?? '—' }}</span></div>
        <div class="info-row"><span class="info-label">Relationship</span><span class="info-value">{{ $loan['guarantor_relationship'] ?? '—' }}</span></div>
      </div>
      @endif
    </div>
  </div>
  @endif

  <div class="section-title">Documents Sighted at Intake</div>
  <table class="checklist">
    <thead>
      <tr>
        <th style="width:12%;">Sighted</th>
        <th>Document</th>
      </tr>
    </thead>
    <tbody>
      <tr><td><span class="box"></span></td><td>National ID / Passport (original + copy)</td></tr>
      <tr><td><span class="box"></span></td><td>Proof of address (utility bill, lease, or equivalent)</td></tr>
      <tr><td><span class="box"></span></td><td>Proof of income (payslip, bank statement, or business records)</td></tr>
      <tr><td><span class="box"></span></td><td>Passport-size photograph</td></tr>
      <tr><td><span class="box"></span></td><td>Guarantor ID (if applicable)</td></tr>
    </tbody>
  </table>

  <div class="section-title">Declaration &amp; Consent</div>
  <div class="declaration">
    I declare that the information provided in this application is true and complete to the best of my knowledge, and I understand that any false statement may result in rejection of this application or termination of any resulting loan.
    I consent to {{ $company }} verifying this information, including with credit reference bureaus and third parties, for the purpose of assessing this application and managing the loan if approved.
    I understand that submission of this form does not guarantee approval and that final terms are subject to {{ $company }}'s assessment and the loan agreement issued upon approval.
  </div>

  <div class="sig-section">
    <div class="sig-col">
      <div class="sig-line">
        <div class="sig-name">{{ $loan['borrower']['name'] }}</div>
        <div class="sig-role">Applicant Signature &amp; Date</div>
      </div>
    </div>
    <div class="sig-col">
      <div class="sig-line">
        <div class="sig-name">{{ $loan['created_by'] ?? 'Loan Officer' }}</div>
        <div class="sig-role">Received By (Loan Officer) &amp; Date</div>
      </div>
    </div>
  </div>

  <div class="notice">
    <strong>Notice:</strong> This form records a loan request only and is not a loan offer. Approved applicants will receive a separate Loan Agreement setting out final terms for signature.
  </div>

  <div class="footer">
    @if(!empty($invoice_footer))
      {{ $invoice_footer }}<br>
    @endif
    &copy; {{ date('Y') }} {{ $company }}. This document was generated on {{ $generatedAt }}. | Loan Application — {{ $loan['loan_number'] }}
  </div>

</div>
</body>
</html>
