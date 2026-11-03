{{--
    Shared frame for every generated document. Rendered by dompdf, so layout is
    table-based (no flexbox, grid or gradients) and the logo is a data URI.

    Child templates provide:
      @section('title')    document title, e.g. "Payment Receipt"
      @section('meta')     right-hand header lines (number, date, status…)
      @section('ref')      short reference for the footer
      @section('content')  the body
--}}
@php($brand ??= \App\Support\DocumentBrand::current())
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>@yield('title') | @yield('ref')</title>
<style>
  @page { margin: 0 0 58px 0; }
  /* Not "*": resetting <html> too wipes the page margin and dompdf drops the fixed footer. */
  body, body * { margin: 0; padding: 0; }
  body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5px; color: #1f2937; line-height: 1.45; }
  table { border-collapse: collapse; }

  /* Header band */
  .band { background: {{ $brand['tint'] }}; border-bottom: 3px solid {{ $brand['color'] }}; padding: 24px 36px 18px; }
  .band-table { width: 100%; }
  .band-table td { vertical-align: top; }
  .logo { max-height: 44px; max-width: 190px; margin-bottom: 6px; }
  .org-name { font-size: 14px; font-weight: bold; color: #111827; }
  .org-lines { font-size: 8px; color: #4b5563; line-height: 1.55; margin-top: 4px; }
  .org-reg { font-size: 7.5px; color: #6b7280; margin-top: 3px; }
  .doc-title { font-size: 17px; font-weight: bold; color: {{ $brand['color'] }}; letter-spacing: 0.6px; text-transform: uppercase; text-align: right; }
  .doc-meta { font-size: 8.5px; color: #4b5563; text-align: right; line-height: 1.65; margin-top: 6px; }
  .doc-meta strong { color: #111827; }

  .content { padding: 4px 36px 0; }

  /* Sections */
  .section { font-size: 8.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px; color: {{ $brand['color'] }};
             border-bottom: 1px solid {{ $brand['border'] }}; padding-bottom: 4px; margin: 16px 0 7px; }
  .muted { color: #6b7280; }
  .small { font-size: 8px; }
  .right { text-align: right; }
  .center { text-align: center; }
  .bold { font-weight: bold; }

  /* Key / value pairs, usually two columns side by side */
  .cols { width: 100%; }
  .cols > tbody > tr > td { width: 50%; vertical-align: top; }
  .cols > tbody > tr > td.gap { width: 4%; }
  .kv { width: 100%; }
  .kv td { padding: 2.5px 0; vertical-align: top; }
  .kv td.k { color: #6b7280; width: 40%; }
  .kv td.v { color: #111827; font-weight: bold; }
  /* Several label/value pairs per row: widths come from the template's own cells. */
  .kv-wide td.k { width: auto; padding-right: 8px; white-space: nowrap; }
  .kv-wide td.v { padding-right: 14px; white-space: nowrap; }

  /* Headline figures */
  .stats { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 10px -6px 2px; }
  .stats td { background: {{ $brand['tint'] }}; border: 1px solid {{ $brand['border'] }}; padding: 8px 10px; vertical-align: top; }
  .stat-label { font-size: 7px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.6px; color: #6b7280; }
  .stat-value { font-size: 12.5px; font-weight: bold; color: #111827; margin-top: 2px; }
  .stat-value.brand { color: {{ $brand['color'] }}; }

  /* Data tables */
  .grid { width: 100%; }
  .grid th { background: {{ $brand['color'] }}; color: {{ $brand['on_color'] }}; font-size: 7.5px; font-weight: bold;
             text-transform: uppercase; letter-spacing: 0.5px; padding: 6px 8px; text-align: left; }
  .grid td { padding: 5px 8px; border-bottom: 1px solid #eef0f3; }
  .grid tr.alt td { background: #fafbfc; }
  .grid .num { text-align: right; }
  .grid tfoot td { font-weight: bold; border-top: 1.5px solid {{ $brand['color'] }}; border-bottom: 0; background: {{ $brand['tint'] }}; }

  /* Right-aligned totals, as on an invoice */
  .totals { width: 46%; float: right; margin-top: 8px; }
  .totals td { padding: 3px 8px; }
  .totals td.v { text-align: right; font-weight: bold; color: #111827; }
  .totals tr.grand td { border-top: 1.5px solid {{ $brand['color'] }}; padding-top: 6px; font-size: 11px; color: {{ $brand['color'] }}; }
  .clear { clear: both; }

  .pill { padding: 1px 7px; font-size: 7.5px; font-weight: bold; }
  .pill-green { background: #dcfce7; color: #166534; }
  .pill-amber { background: #fef3c7; color: #92400e; }
  .pill-red { background: #fee2e2; color: #991b1b; }
  .pill-grey { background: #f3f4f6; color: #374151; }
  .pill-brand { background: {{ $brand['tint'] }}; color: {{ $brand['color'] }}; border: 1px solid {{ $brand['border'] }}; }

  .note { background: #f9fafb; border-left: 3px solid {{ $brand['color'] }}; padding: 7px 10px; font-size: 8px; color: #4b5563; margin-top: 12px; }
  .terms { font-size: 8px; color: #374151; line-height: 1.6; }
  .terms li { margin-bottom: 2px; }

  /* Signatures */
  .sig { width: 100%; margin-top: 30px; }
  .sig td { width: 46%; vertical-align: bottom; }
  .sig td.gap { width: 8%; }
  .sig-line { border-top: 1px solid #374151; padding-top: 5px; }
  .sig-name { font-weight: bold; color: #111827; }

  /* Footer on every page */
  .footer { position: fixed; bottom: -44px; left: 36px; right: 36px; height: 30px; border-top: 1px solid #e5e7eb; padding-top: 6px; font-size: 7px; color: #9ca3af; }
  .footer table { width: 100%; }
  .pagenum:before { content: counter(page); }
  .powered { color: #6b7280; }
  .powered strong { color: #111827; letter-spacing: 0.4px; }
</style>
</head>
<body>

{{-- Plain blocks, not a table: dompdf drops a table inside a fixed-position footer. --}}
<div class="footer">
  <span class="powered" style="float: right;">Powered by <strong>LENDR</strong> &middot; lendr.cc</span>
  @if($brand['footer']){{ $brand['footer'] }} &middot; @endif
  @yield('ref') &middot; Generated {{ $generatedAt ?? now()->format('d M Y H:i') }} &middot; Page <span class="pagenum"></span>
</div>

<div class="band">
  <table class="band-table">
    <tr>
      <td style="width: 58%;">
        @if($brand['logo'])
          <img class="logo" src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }}"><br>
        @endif
        <div class="org-name">{{ $brand['name'] }}</div>
        <div class="org-lines">
          @if($brand['address']){!! nl2br(e($brand['address'])) !!}<br>@endif
          {{ collect([$brand['phone'], $brand['email'], $brand['website']])->filter()->implode('  ·  ') }}
        </div>
        @if($brand['registration'])
          <div class="org-reg">
            @foreach($brand['registration'] as $label => $value){{ $label }}: {{ $value }}@if(! $loop->last) &nbsp;·&nbsp; @endif @endforeach
          </div>
        @endif
      </td>
      <td style="width: 42%;">
        <div class="doc-title">@yield('title')</div>
        <div class="doc-meta">@yield('meta')</div>
      </td>
    </tr>
  </table>
</div>

<div class="content">
  @yield('content')
</div>

</body>
</html>
