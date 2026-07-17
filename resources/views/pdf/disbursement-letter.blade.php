<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Disbursement Letter — {{ $loan['loan_number'] }}</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; background: #fff; line-height: 1.6; }
  .header { background: linear-gradient(135deg, #059669 0%, #047857 100%); color: #fff; padding: 28px 40px; display: flex; justify-content: space-between; align-items: flex-start; }
  .header-left h1 { font-size: 22px; font-weight: 700; }
  .header-left p { font-size: 11px; opacity: 0.85; margin-top: 3px; }
  .header-right { text-align: right; }
  .header-right .doc-type { font-size: 14px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
  .header-right .ref { font-size: 10px; opacity: 0.8; margin-top: 4px; }
  .stripe { height: 4px; background: linear-gradient(90deg, #10b981, #34d399, #6ee7b7); }
  .content { padding: 32px 40px; }
  .date-line { text-align: right; color: #6b7280; margin-bottom: 24px; font-size: 10.5px; }
  .section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: #059669; border-bottom: 1.5px solid #d1fae5; padding-bottom: 5px; margin-bottom: 12px; margin-top: 20px; }
  .info-grid { display: table; width: 100%; }
  .info-row { display: table-row; }
  .info-label { display: table-cell; width: 40%; color: #6b7280; font-size: 10px; padding: 3px 0; vertical-align: top; }
  .info-value { display: table-cell; font-weight: 600; font-size: 10.5px; color: #111827; padding: 3px 0; }
  .two-col { width: 100%; display: table; border-spacing: 16px 0; }
  .col { display: table-cell; width: 50%; vertical-align: top; padding: 0 8px; }
  .amount-box { background: #ecfdf5; border: 1.5px solid #6ee7b7; border-radius: 6px; padding: 14px 16px; margin: 16px 0; text-align: center; }
  .amount-box .label { font-size: 10px; color: #059669; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
  .amount-box .value { font-size: 22px; font-weight: 700; color: #065f46; margin-top: 4px; }
  .notice { background: #f0fdf4; border-left: 3px solid #10b981; padding: 10px 14px; margin: 16px 0; font-size: 10px; color: #374151; }
  .footer { margin-top: 40px; padding: 16px 40px; border-top: 1px solid #e5e7eb; font-size: 9.5px; color: #9ca3af; text-align: center; }
  .signature-area { display: table; width: 100%; margin-top: 40px; }
  .sig-col { display: table-cell; width: 45%; vertical-align: bottom; padding-right: 30px; }
  .sig-line { border-top: 1px solid #374151; padding-top: 6px; font-size: 10px; color: #374151; margin-top: 50px; }
</style>
</head>
<body>

<div class="header">
  <div class="header-left">
    <h1>{{ $company }}</h1>
    <p>{{ $address }}</p>
    <p>{{ $phone }} &bull; {{ $email }}</p>
  </div>
  <div class="header-right">
    <div class="doc-type">Disbursement Letter</div>
    <div class="ref">Ref: {{ $loan['loan_number'] }}</div>
  </div>
</div>
<div class="stripe"></div>

<div class="content">

  <div class="date-line">{{ $generatedAt }}</div>

  <p style="margin-bottom:6px; font-weight:600; font-size:12px;">Dear {{ $loan['borrower']['name'] }},</p>

  <p style="margin-bottom:12px; font-size:10.5px;">
    We are pleased to inform you that your loan application has been approved and the funds have been
    disbursed as detailed below. Please retain this letter for your records.
  </p>

  <div class="amount-box">
    <div class="label">Disbursed Amount</div>
    <div class="value">{{ $loan['currency'] }} {{ $loan['principal_amount'] }}</div>
  </div>

  <div class="two-col">
    <div class="col">
      <div class="section-title">Borrower Details</div>
      <div class="info-grid">
        <div class="info-row"><div class="info-label">Name</div><div class="info-value">{{ $loan['borrower']['name'] }}</div></div>
        <div class="info-row"><div class="info-label">Borrower No.</div><div class="info-value">{{ $loan['borrower']['borrower_number'] }}</div></div>
        <div class="info-row"><div class="info-label">Phone</div><div class="info-value">{{ $loan['borrower']['phone'] }}</div></div>
        @if($loan['borrower']['city'])
        <div class="info-row"><div class="info-label">City</div><div class="info-value">{{ $loan['borrower']['city'] }}</div></div>
        @endif
      </div>
    </div>
    <div class="col">
      <div class="section-title">Loan Details</div>
      <div class="info-grid">
        <div class="info-row"><div class="info-label">Loan Number</div><div class="info-value">{{ $loan['loan_number'] }}</div></div>
        <div class="info-row"><div class="info-label">Loan Type</div><div class="info-value">{{ $loan['loan_type'] }}</div></div>
        <div class="info-row"><div class="info-label">Interest Rate</div><div class="info-value">{{ $loan['interest_rate'] }}% ({{ $loan['interest_type'] }})</div></div>
        <div class="info-row"><div class="info-label">Tenure</div><div class="info-value">{{ $loan['tenure'] }} {{ $loan['tenure_type'] }}</div></div>
      </div>
    </div>
  </div>

  <div class="section-title">Disbursement & Repayment</div>
  <div class="info-grid">
    <div class="info-row"><div class="info-label">Disbursement Date</div><div class="info-value">{{ $loan['disbursement_date'] ?? 'N/A' }}</div></div>
    <div class="info-row"><div class="info-label">Disbursement Method</div><div class="info-value">{{ $loan['disbursement_method'] ?? 'N/A' }}</div></div>
    @if($loan['disbursement_account'])
    <div class="info-row"><div class="info-label">Disbursement Account</div><div class="info-value">{{ $loan['disbursement_account'] }}</div></div>
    @endif
    <div class="info-row"><div class="info-label">First Repayment Date</div><div class="info-value">{{ $loan['first_repayment_date'] ?? 'N/A' }}</div></div>
    <div class="info-row"><div class="info-label">Loan Maturity Date</div><div class="info-value">{{ $loan['maturity_date'] ?? 'N/A' }}</div></div>
    @if($loan['loan_purpose'])
    <div class="info-row"><div class="info-label">Loan Purpose</div><div class="info-value">{{ $loan['loan_purpose'] }}</div></div>
    @endif
  </div>

  <div class="notice">
    <strong>Important:</strong> Please ensure repayments are made on or before the due dates to avoid penalties.
    Contact us immediately if you anticipate any difficulty meeting your obligations.
  </div>

  <div class="signature-area">
    <div class="sig-col">
      <div class="sig-line">
        <strong>Authorised Officer</strong><br>
        {{ $loan['disbursed_by'] ?? $company }}<br>
        {{ $generatedAt }}
      </div>
    </div>
    <div class="sig-col">
      <div class="sig-line">
        <strong>Borrower Acknowledgement</strong><br>
        {{ $loan['borrower']['name'] }}<br>
        Date: ___________________
      </div>
    </div>
  </div>

</div>

<div class="footer">
  {{ $invoice_footer ?? $company.' — '.$address }}<br>
  Generated {{ $generatedAt }}
</div>

</body>
</html>
