@props(['value', 'label' => null, 'variant' => 'brand'])
@php
    [$bg, $fg] = match ($variant) {
        'warning' => ['#FFFAEB', '#B54708'],
        'danger' => ['#FEF3F2', '#B42318'],
        default => [$brand['tint'], $brand['accent']],
    };
@endphp
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 0 0 24px;">
    <tr>
        <td align="center" style="background: {{ $bg }}; border-radius: 12px; padding: 24px;">
            <div style="font-size: 40px; font-weight: 800; line-height: 1.1; color: {{ $fg }}; letter-spacing: -0.03em;">{{ $value }}</div>
            @if ($label)
                <div style="font-size: 13px; color: #667085; margin-top: 6px;">{{ $label }}</div>
            @endif
        </td>
    </tr>
</table>
