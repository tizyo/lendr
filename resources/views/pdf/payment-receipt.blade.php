<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payment Receipt — {{ $payment['receipt_number'] }}</title>
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

  .content { padding: 32px 40px; }

  .receipt-badge { display: inline-block; background: #ecfdf5; border: 1.5px solid #6ee7b7; border-radius: 6px; padding: 6px 16px; font-size: 12px; font-weight: 700; color: #065f46; letter-spacing: 0.5px; margin-bottom: 20px; }

  .section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: #059669; border-bottom: 1.5px solid #d1fae5; padding-bottom: 5px; margin-bottom: 12px; margin-top: 20px; }

  .info-grid { display: table; width: 100%; }
  .info-row { display: table-row; }
  .info-label { display: table-cell; width: 38%; color: #6b7280; font-size: 10px; padding: 4px 0; }
  .info-value { display: table-cell; font-weight: 600; font-size: 10.5px; color: #111827; padding: 4px 0; }

  .amount-hero { text-align: center; background: linear-gradient(135deg, #ecfdf5, #d1fae5); border: 2px solid #6ee7b7; border-radius: 10px; padding: 24px; margin: 20px 0; }
  .amount-hero .label { font-size: 11px; color: #059669; font-weight: 600; text-transform: uppercase; letter-spacing: 0.8px; }
  .amount-hero .value { font-size: 30px; font-weight: 700; color: #065f46; margin: 6px 0 2px; }
  .amount-hero .sub { font-size: 10px; color: #6b7280; }

  .allocation-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
  .allocation-table th { background: #f0fdf4; color: #065f46; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 8px 12px; border: 1px solid #d1fae5; text-align: left; }
  .allocation-table td { padding: 8px 12px; border: 1px solid #e5e7eb; font-size: 10.5px; }
  .allocation-table tr:nth-child(even) td { background: #f9fafb; }
  .allocation-table .total-row td { font-weight: 700; background: #ecfdf5; border-color: #6ee7b7; }

  .verified-stamp { text-align: center; margin-top: 24px; }
  .verified-stamp .stamp { display: inline-block; border: 2.5px solid #059669; border-radius: 50%; width: 64px; height: 64px; line-height: 60px; font-size: 9px; font-weight: 700; color: #059669; letter-spacing: 0.5px; text-transform: uppercase; }

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
    <div class="doc-type">Official Receipt</div>
    <div class="ref">Receipt: {{ $payment['receipt_number'] }}</div>
    <div class="ref">Generated: {{ $generatedAt }}</div>
  </div>
</div>
<div class="stripe"></div>

<div class="content">

  <div class="receipt-badge">PAYMENT RECEIPT</div>

  <div class="amount-hero">
    <div class="label">Amount Received</div>
    <div class="value">{{ $currency }} {{ $payment['amount'] }}</div>
    <div class="sub">{{ $payment['payment_method'] }} — {{ $payment['payment_date'] }}</div>
  </div>

  <div class="section-title">Borrower &amp; Loan</div>
  <div class="info-grid">
    <div class="info-row"><span class="info-label">Borrower Name</span><span class="info-value">{{ $payment['borrower_name'] }}</span></div>
    <div class="info-row"><span class="info-label">Borrower Number</span><span class="info-value">{{ $payment['borrower_number'] }}</span></div>
    <div class="info-row"><span class="info-label">Loan Number</span><span class="info-value">{{ $payment['loan_number'] }}</span></div>
    <div class="info-row"><span class="info-label">Loan Type</span><span class="info-value">{{ $payment['loan_type'] }}</span></div>
  </div>

  <div class="section-title">Payment Details</div>
  <div class="info-grid">
    <div class="info-row"><span class="info-label">Receipt Number</span><span class="info-value">{{ $payment['receipt_number'] }}</span></div>
    <div class="info-row"><span class="info-label">Payment Method</span><span class="info-value">{{ $payment['payment_method'] }}</span></div>
    <div class="info-row"><span class="info-label">Payment Date</span><span class="info-value">{{ $payment['payment_date'] }}</span></div>
    @if($payment['reference'])
    <div class="info-row"><span class="info-label">Reference</span><span class="info-value">{{ $payment['reference'] }}</span></div>
    @endif
    <div class="info-row"><span class="info-label">Recorded By</span><span class="info-value">{{ $payment['recorded_by'] ?? '—' }}</span></div>
  </div>

  <div class="section-title">Payment Allocation</div>
  <table class="allocation-table">
    <thead>
      <tr>
        <th>Component</th>
        <th style="text-align:right;">Amount ({{ $currency }})</th>
      </tr>
    </thead>
    <tbody>
      <tr><td>Principal</td><td style="text-align:right;">{{ $payment['principal_allocated'] }}</td></tr>
      <tr><td>Interest</td><td style="text-align:right;">{{ $payment['interest_allocated'] }}</td></tr>
      <tr><td>Penalty / Charges</td><td style="text-align:right;">{{ $payment['penalty_allocated'] }}</td></tr>
      <tr class="total-row"><td>Total Paid</td><td style="text-align:right;">{{ $payment['amount'] }}</td></tr>
    </tbody>
  </table>

  @if($payment['notes'])
  <div style="margin-top:16px; padding:10px 14px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:5px; font-size:10px; color:#374151;">
    <strong>Notes:</strong> {{ $payment['notes'] }}
  </div>
  @endif

  <div class="verified-stamp">
    <div class="stamp">PAID</div>
  </div>

  <p style="text-align:center; font-size:9.5px; color:#6b7280; margin-top:10px;">
    This receipt confirms that the above payment has been received and applied to the loan account.
  </p>

  <div class="footer">
    @if(!empty($invoice_footer))
      {{ $invoice_footer }}<br>
    @endif
    &copy; {{ date('Y') }} {{ $company }}. Generated on {{ $generatedAt }}. | Receipt No. {{ $payment['receipt_number'] }}
  </div>

</div>
</body>
</html>
