@props(['rows' => [], 'mono' => []])
{{-- Label / value list. Null or empty values are skipped so callers can pass optional rows.
     $mono lists the labels whose values should be monospaced (usernames, passwords). --}}
@php($rows = array_filter($rows, fn ($v) => $v !== null && $v !== ''))
@if (count($rows))
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%"
       style="margin: 0 0 24px; background: {{ $brand['tint'] }}; border-radius: 12px;">
    <tr>
        <td style="padding: 8px 22px;">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                @foreach ($rows as $label => $value)
                    <tr>
                        <td style="padding: 10px 0; font-size: 13px; color: #667085; {{ $loop->last ? '' : 'border-bottom: 1px solid '.$brand['tint_border'].';' }}" valign="top">
                            {{ $label }}
                        </td>
                        <td align="right" style="padding: 10px 0 10px 16px; font-size: 14px; font-weight: 600; color: #101828; word-break: break-word; {{ in_array($label, (array) $mono, true) ? "font-family: 'SF Mono', Menlo, Consolas, monospace; letter-spacing: 0.01em;" : '' }} {{ $loop->last ? '' : 'border-bottom: 1px solid '.$brand['tint_border'].';' }}" valign="top">
                            {{ $value }}
                        </td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>
@endif
