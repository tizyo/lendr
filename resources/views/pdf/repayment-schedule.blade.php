<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Repayment Schedule — {{ $loan['loan_number'] }}</title>
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

  .meta-bar { background: #f0fdf4; border: 1.5px solid #d1fae5; border-radius: 6px; padding: 12px 18px; display: table; width: 100%; margin-bottom: 20px; }
  .meta-item { display: table-cell; text-align: center; }
  .meta-item .meta-label { font-size: 9px; color: #059669; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
  .meta-item .meta-value { font-size: 13px; font-weight: 700; color: #065f46; margin-top: 2px; }

  .info-grid { display: table; width: 100%; margin-bottom: 16px; }
  .info-row { display: table-row; }
  .info-label { display: table-cell; width: 30%; color: #6b7280; font-size: 10px; padding: 3px 0; }
  .info-value { display: table-cell; font-weight: 600; font-size: 10.5px; color: #111827; padding: 3px 0; }

  .section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: #059669; border-bottom: 1.5px solid #d1fae5; padding-bottom: 5px; margin-bottom: 12px; margin-top: 20px; }

  .schedule-table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 10.5px; }
  .schedule-table th { background: #065f46; color: #fff; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; padding: 8px 10px; border: 1px solid #047857; text-align: right; }
  .schedule-table th:first-child { text-align: center; }
  .schedule-table th:nth-child(2) { text-align: left; }
  .schedule-table td { padding: 7px 10px; border: 1px solid #e5e7eb; text-align: right; }
  .schedule-table td:first-child { text-align: center; font-weight: 600; }
  .schedule-table td:nth-child(2) { text-align: left; }
  .schedule-table tr:nth-child(even) td { background: #f9fafb; }
  .paid-row td { background: #ecfdf5 !important; }
  .overdue-row td { background: #fef2f2 !important; }
  .status-badge { display: inline-block; padding: 2px 7px; border-radius: 10px; font-size: 9px; font-weight: 700; }
  .badge-paid { background: #d1fae5; color: #065f46; }
  .badge-overdue { background: #fee2e2; color: #991b1b; }
  .badge-pending { background: #f3f4f6; color: #374151; }

  .totals-row td { font-weight: 700; background: #f0fdf4 !important; border-top: 2px solid #059669; color: #065f46; }

  .summary-box { display: table; width: 100%; margin-top: 20px; }
  .summary-col { display: table-cell; width: 33%; padding: 0 6px; vertical-align: top; }
  .summary-card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 14px; text-align: center; }
  .summary-card .s-label { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; }
  .summary-card .s-value { font-size: 14px; font-weight: 700; color: #111827; margin-top: 2px; }
  .summary-card.green { border-color: #6ee7b7; background: #ecfdf5; }
  .summary-card.green .s-value { color: #065f46; }

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
    <div class="doc-type">Repayment Schedule</div>
    <div class="ref">Loan: {{ $loan['loan_number'] }}</div>
    <div class="ref">Generated: {{ $generatedAt }}</div>
  </div>
</div>
<div class="stripe"></div>

<div class="content">

  <div class="meta-bar">
    <div class="meta-item">
      <div class="meta-label">Borrower</div>
      <div class="meta-value">{{ $loan['borrower']['name'] }}</div>
    </div>
    <div class="meta-item">
      <div class="meta-label">Loan Number</div>
      <div class="meta-value">{{ $loan['loan_number'] }}</div>
    </div>
    <div class="meta-item">
      <div class="meta-label">Principal</div>
      <div class="meta-value">{{ $currency }} {{ $loan['principal_amount'] }}</div>
    </div>
    <div class="meta-item">
      <div class="meta-label">Total Repayable</div>
      <div class="meta-value">{{ $currency }} {{ $loan['total_payable'] }}</div>
    </div>
    <div class="meta-item">
      <div class="meta-label">Status</div>
      <div class="meta-value">{{ $loan['status_label'] }}</div>
    </div>
  </div>

  <div class="info-grid">
    <div class="info-row"><span class="info-label">Interest Rate</span><span class="info-value">{{ $loan['interest_rate'] }}% {{ $loan['interest_type'] }}</span></div>
    <div class="info-row"><span class="info-label">Repayment Schedule</span><span class="info-value">{{ ucfirst($loan['repayment_schedule']) }}</span></div>
    <div class="info-row"><span class="info-label">Tenure</span><span class="info-value">{{ $loan['tenure'] }} {{ $loan['tenure_type'] }}</span></div>
    @if($loan['first_repayment_date'])<div class="info-row"><span class="info-label">First Repayment</span><span class="info-value">{{ $loan['first_repayment_date'] }}</span></div>@endif
    @if($loan['maturity_date'])<div class="info-row"><span class="info-label">Maturity Date</span><span class="info-value">{{ $loan['maturity_date'] }}</span></div>@endif
  </div>

  <div class="section-title">Instalment Schedule</div>
  <table class="schedule-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Due Date</th>
        <th>Total Due ({{ $currency }})</th>
        <th>Total Paid ({{ $currency }})</th>
        <th>Outstanding ({{ $currency }})</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      @php $totalDue = 0; $totalPaid = 0; $totalOutstanding = 0; @endphp
      @foreach($loan['schedule'] as $row)
        @php
          $totalDue += (float) str_replace(',', '', $row['total_due']);
          $totalPaid += (float) str_replace(',', '', $row['total_paid']);
          $totalOutstanding += (float) str_replace(',', '', $row['outstanding']);
          $rowClass = $row['is_paid'] ? 'paid-row' : ($row['days_overdue'] > 0 ? 'overdue-row' : '');
        @endphp
        <tr class="{{ $rowClass }}">
          <td>{{ $row['instalment_number'] }}</td>
          <td>{{ $row['due_date'] }}</td>
          <td>{{ $row['total_due'] }}</td>
          <td>{{ $row['total_paid'] }}</td>
          <td>{{ $row['outstanding'] }}</td>
          <td style="text-align:center;">
            @if($row['is_paid'])
              <span class="status-badge badge-paid">Paid</span>
            @elseif($row['days_overdue'] > 0)
              <span class="status-badge badge-overdue">Overdue {{ $row['days_overdue'] }}d</span>
            @else
              <span class="status-badge badge-pending">Pending</span>
            @endif
          </td>
        </tr>
      @endforeach
      <tr class="totals-row">
        <td colspan="2">Totals</td>
        <td>{{ number_format($totalDue, 2) }}</td>
        <td>{{ number_format($totalPaid, 2) }}</td>
        <td>{{ number_format($totalOutstanding, 2) }}</td>
        <td></td>
      </tr>
    </tbody>
  </table>

  <div class="summary-box">
    <div class="summary-col" style="padding-left:0;">
      <div class="summary-card">
        <div class="s-label">Total Paid</div>
        <div class="s-value">{{ $currency }} {{ $loan['total_paid'] }}</div>
      </div>
    </div>
    <div class="summary-col">
      <div class="summary-card green">
        <div class="s-label">Outstanding Balance</div>
        <div class="s-value">{{ $currency }} {{ $loan['outstanding_balance'] }}</div>
      </div>
    </div>
    <div class="summary-col" style="padding-right:0;">
      <div class="summary-card">
        <div class="s-label">Penalty Balance</div>
        <div class="s-value">{{ $currency }} {{ $loan['penalty_balance'] }}</div>
      </div>
    </div>
  </div>

  <div class="footer">
    @if(!empty($invoice_footer))
      {{ $invoice_footer }}<br>
    @endif
    &copy; {{ date('Y') }} {{ $company }}. Generated on {{ $generatedAt }}. | Repayment Schedule — {{ $loan['loan_number'] }}
  </div>

</div>
</body>
</html>
