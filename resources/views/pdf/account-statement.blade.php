<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Account Statement — {{ $borrower['borrower_number'] }}</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; background: #fff; line-height: 1.5; }

  .header { background: linear-gradient(135deg, #059669 0%, #047857 100%); color: #fff; padding: 28px 40px; display: flex; justify-content: space-between; align-items: flex-start; }
  .header-left h1 { font-size: 22px; font-weight: 700; letter-spacing: -0.3px; }
  .header-left p { font-size: 11px; opacity: 0.85; margin-top: 3px; }
  .header-right { text-align: right; }
  .header-right .doc-type { font-size: 14px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
  .header-right .ref { font-size: 10px; opacity: 0.8; margin-top: 4px; }
  .stripe { height: 4px; background: linear-gradient(90deg, #10b981, #34d399, #6ee7b7); }

  .content { padding: 28px 40px; }

  .section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: #059669; border-bottom: 1.5px solid #d1fae5; padding-bottom: 5px; margin-bottom: 12px; margin-top: 20px; }

  .info-grid { display: table; width: 100%; }
  .info-row { display: table-row; }
  .info-label { display: table-cell; width: 36%; color: #6b7280; font-size: 10px; padding: 3px 0; }
  .info-value { display: table-cell; font-weight: 600; font-size: 10.5px; color: #111827; padding: 3px 0; }

  .kpi-bar { display: table; width: 100%; margin: 16px 0; }
  .kpi-item { display: table-cell; text-align: center; padding: 12px 8px; background: #f0fdf4; border: 1px solid #d1fae5; }
  .kpi-item:first-child { border-radius: 6px 0 0 6px; border-right: none; }
  .kpi-item:last-child { border-radius: 0 6px 6px 0; }
  .kpi-item:not(:first-child):not(:last-child) { border-right: none; }
  .kpi-label { font-size: 9px; color: #059669; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
  .kpi-value { font-size: 13px; font-weight: 700; color: #065f46; margin-top: 2px; }

  .loan-card { border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; margin-bottom: 20px; }
  .loan-card-header { background: #f0fdf4; border-bottom: 1px solid #d1fae5; padding: 10px 16px; display: table; width: 100%; }
  .loan-card-header .ln { display: table-cell; font-size: 12px; font-weight: 700; color: #065f46; }
  .loan-card-header .ls { display: table-cell; text-align: right; font-size: 10px; }
  .loan-card-body { padding: 12px 16px; }
  .two-col { display: table; width: 100%; }
  .col { display: table-cell; width: 50%; vertical-align: top; padding-right: 16px; }

  .payments-table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 10px; }
  .payments-table th { background: #065f46; color: #fff; font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; padding: 7px 10px; border: 1px solid #047857; text-align: right; }
  .payments-table th:first-child, .payments-table th:nth-child(2) { text-align: left; }
  .payments-table td { padding: 6px 10px; border: 1px solid #e5e7eb; text-align: right; }
  .payments-table td:first-child, .payments-table td:nth-child(2) { text-align: left; }
  .payments-table tr:nth-child(even) td { background: #f9fafb; }

  .status-badge { display: inline-block; padding: 2px 7px; border-radius: 10px; font-size: 9px; font-weight: 700; }
  .badge-active { background: #d1fae5; color: #065f46; }
  .badge-completed { background: #dbeafe; color: #1e40af; }
  .badge-defaulted { background: #fee2e2; color: #991b1b; }
  .badge-disbursed { background: #ede9fe; color: #5b21b6; }
  .badge-other { background: #f3f4f6; color: #374151; }

  .notice { background: #f0fdf4; border: 1px solid #d1fae5; border-radius: 5px; padding: 10px 14px; margin-top: 20px; font-size: 9.5px; color: #065f46; line-height: 1.6; }
  .footer { margin-top: 24px; border-top: 1px solid #e5e7eb; padding-top: 10px; text-align: center; font-size: 9px; color: #9ca3af; }

  .page-break { page-break-before: always; }
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
    <div class="doc-type">Account Statement</div>
    <div class="ref">{{ $borrower['borrower_number'] }}</div>
    <div class="ref">Generated: {{ $generatedAt }}</div>
  </div>
</div>
<div class="stripe"></div>

<div class="content">

  <div class="section-title">Borrower Profile</div>
  <div style="display:table; width:100%;">
    <div style="display:table-cell; width:50%; vertical-align:top;">
      <div class="info-grid">
        <div class="info-row"><span class="info-label">Full Name</span><span class="info-value">{{ $borrower['name'] }}</span></div>
        <div class="info-row"><span class="info-label">Borrower Number</span><span class="info-value">{{ $borrower['borrower_number'] }}</span></div>
        <div class="info-row"><span class="info-label">Phone</span><span class="info-value">{{ $borrower['phone'] }}</span></div>
        @if(!empty($borrower['email']))<div class="info-row"><span class="info-label">Email</span><span class="info-value">{{ $borrower['email'] }}</span></div>@endif
      </div>
    </div>
    <div style="display:table-cell; width:50%; vertical-align:top; padding-left:16px;">
      <div class="info-grid">
        @if(!empty($borrower['city']))<div class="info-row"><span class="info-label">City</span><span class="info-value">{{ $borrower['city'] }}</span></div>@endif
        <div class="info-row"><span class="info-label">KYC Status</span><span class="info-value">{{ $borrower['kyc_verified'] ? 'Verified' : 'Pending' }}</span></div>
        @if(!empty($borrower['credit_score']))<div class="info-row"><span class="info-label">Credit Score</span><span class="info-value">{{ $borrower['credit_score'] }}</span></div>@endif
        <div class="info-row"><span class="info-label">Statement Date</span><span class="info-value">{{ $generatedAt }}</span></div>
      </div>
    </div>
  </div>

  <div class="kpi-bar">
    <div class="kpi-item">
      <div class="kpi-label">Total Loans</div>
      <div class="kpi-value">{{ count($loans) }}</div>
    </div>
    <div class="kpi-item">
      <div class="kpi-label">Total Disbursed</div>
      <div class="kpi-value">{{ $currency }} {{ number_format($summary['total_disbursed'], 2) }}</div>
    </div>
    <div class="kpi-item">
      <div class="kpi-label">Total Paid</div>
      <div class="kpi-value">{{ $currency }} {{ number_format($summary['total_paid'], 2) }}</div>
    </div>
    <div class="kpi-item">
      <div class="kpi-label">Outstanding</div>
      <div class="kpi-value">{{ $currency }} {{ number_format($summary['outstanding'], 2) }}</div>
    </div>
  </div>

  @foreach($loans as $loan)
  <div class="loan-card">
    <div class="loan-card-header">
      <div class="ln">{{ $loan['loan_number'] }} — {{ $loan['loan_type'] }}</div>
      <div class="ls">
        @php
          $sc = match($loan['status']) {
            'active', 'disbursed' => 'badge-active',
            'completed' => 'badge-completed',
            'defaulted', 'written_off' => 'badge-defaulted',
            'approved' => 'badge-disbursed',
            default => 'badge-other',
          };
        @endphp
        <span class="status-badge {{ $sc }}">{{ $loan['status_label'] }}</span>
      </div>
    </div>
    <div class="loan-card-body">
      <div class="two-col">
        <div class="col">
          <div class="info-grid">
            <div class="info-row"><span class="info-label">Principal</span><span class="info-value">{{ $currency }} {{ $loan['principal_amount'] }}</span></div>
            <div class="info-row"><span class="info-label">Total Payable</span><span class="info-value">{{ $currency }} {{ $loan['total_payable'] }}</span></div>
            <div class="info-row"><span class="info-label">Total Paid</span><span class="info-value">{{ $currency }} {{ $loan['total_paid'] }}</span></div>
            <div class="info-row"><span class="info-label">Outstanding</span><span class="info-value">{{ $currency }} {{ $loan['outstanding_balance'] }}</span></div>
          </div>
        </div>
        <div class="col">
          <div class="info-grid">
            <div class="info-row"><span class="info-label">Applied</span><span class="info-value">{{ $loan['application_date'] }}</span></div>
            @if(!empty($loan['disbursement_date']))<div class="info-row"><span class="info-label">Disbursed</span><span class="info-value">{{ $loan['disbursement_date'] }}</span></div>@endif
            @if(!empty($loan['maturity_date']))<div class="info-row"><span class="info-label">Maturity</span><span class="info-value">{{ $loan['maturity_date'] }}</span></div>@endif
          </div>
        </div>
      </div>

      @if(count($loan['payments']) > 0)
      <p style="font-size:10px; font-weight:700; color:#374151; margin:12px 0 6px;">Payment History</p>
      <table class="payments-table">
        <thead>
          <tr>
            <th>Receipt</th>
            <th>Date</th>
            <th>Method</th>
            <th>Amount ({{ $currency }})</th>
            <th>Principal</th>
            <th>Interest</th>
          </tr>
        </thead>
        <tbody>
          @foreach($loan['payments'] as $p)
          <tr>
            <td>{{ $p['receipt_number'] }}</td>
            <td>{{ $p['payment_date'] }}</td>
            <td>{{ $p['payment_method'] }}</td>
            <td>{{ $p['amount'] }}</td>
            <td>{{ $p['principal_allocated'] }}</td>
            <td>{{ $p['interest_allocated'] }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @else
      <p style="font-size:10px; color:#9ca3af; margin-top:10px;">No payments recorded for this loan.</p>
      @endif
    </div>
  </div>
  @endforeach

  <div class="notice">
    This statement is a summary of your loan accounts with <strong>{{ $company }}</strong> as at {{ $generatedAt }}.
    For queries or disputes, please contact your loan officer or our support centre.
  </div>

  <div class="footer">
    @if(!empty($invoice_footer))
      {{ $invoice_footer }}<br>
    @endif
    &copy; {{ date('Y') }} {{ $company }}. This document was generated on {{ $generatedAt }} and is for information purposes only.
    | Account Statement — {{ $borrower['borrower_number'] }}
  </div>

</div>
</body>
</html>
