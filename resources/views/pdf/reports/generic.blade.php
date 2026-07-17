<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #1a1a2e; }
  .header { background: #0D47A1; color: #fff; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; }
  .header h1 { font-size: 16px; font-weight: bold; }
  .header .meta { font-size: 9px; text-align: right; }
  .summary-bar { background: #EEF2FF; border-left: 4px solid #0D47A1; padding: 10px 20px; display: flex; gap: 40px; margin-bottom: 12px; }
  .summary-bar .kpi { display: inline-block; }
  .summary-bar .kpi-label { font-size: 8px; color: #555; text-transform: uppercase; }
  .summary-bar .kpi-value { font-size: 13px; font-weight: bold; color: #0D47A1; }
  table { width: 100%; border-collapse: collapse; margin-top: 8px; }
  thead tr { background: #0D47A1; color: #fff; }
  thead th { padding: 6px 8px; text-align: left; font-size: 9px; text-transform: uppercase; }
  tbody tr:nth-child(even) { background: #F5F7FA; }
  tbody tr:hover { background: #EEF2FF; }
  tbody td { padding: 5px 8px; border-bottom: 1px solid #E5E7EB; font-size: 9px; }
  .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8px; color: #888; padding: 6px; border-top: 1px solid #ddd; }
  .page-break { page-break-after: always; }
</style>
</head>
<body>

<div class="header">
  <div>
    <h1>{{ $type }} Report</h1>
    <div style="font-size:9px; margin-top:3px; opacity:0.85;">LENDR Loan Management Platform</div>
  </div>
  <div class="meta">
    Generated: {{ $generated_at }}<br>
    Records: {{ count($rows) }}
  </div>
</div>

@if(!empty($summary))
<div class="summary-bar">
  @foreach($summary as $key => $value)
    @if(!is_array($value))
    <div class="kpi">
      <div class="kpi-label">{{ ucwords(str_replace('_', ' ', $key)) }}</div>
      <div class="kpi-value">{{ is_numeric($value) ? number_format((float)$value, 2) : $value }}</div>
    </div>
    @endif
  @endforeach
</div>
@endif

@if(!empty($rows))
<table>
  <thead>
    <tr>
      @foreach(array_keys((array) $rows[0]) as $col)
        <th>{{ ucwords(str_replace('_', ' ', $col)) }}</th>
      @endforeach
    </tr>
  </thead>
  <tbody>
    @foreach($rows as $row)
    <tr>
      @foreach((array) $row as $value)
        <td>{{ $value ?? '—' }}</td>
      @endforeach
    </tr>
    @endforeach
  </tbody>
</table>
@else
<p style="padding: 20px; text-align: center; color: #888;">No data for this report.</p>
@endif

<div class="footer">
  LENDR &mdash; Confidential &mdash; Page <span class="pagenum"></span>
</div>

</body>
</html>
